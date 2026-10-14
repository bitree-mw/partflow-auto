<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Super admin data cleanup: permanently removes chosen record types, optionally limited to a date range.
 *
 * Vehicle makes/models, car models, brands, product types, sites, roles, tax profiles, fuel types and
 * business settings are never touched. Records still referenced by kept transactions are skipped rather
 * than breaking foreign keys. Deleting stock documents reverses their stock effect, except on a full wipe
 * of every stock document type with no date range, which sets all stock to zero instead.
 */
class DataCleanupService
{
    public const CATEGORIES = [
        'sales' => [
            'group' => 'Transactions',
            'label' => 'Sales and sale returns',
            'hint' => 'Includes line items and customer payments. Sold units are put back into stock.',
        ],
        'purchases' => [
            'group' => 'Transactions',
            'label' => 'Purchases and purchase returns',
            'hint' => 'Includes line items and supplier payments. Received units are taken back out of stock.',
        ],
        'transfers' => [
            'group' => 'Transactions',
            'label' => 'Stock transfers',
            'hint' => 'Moved units go back to the sending site.',
        ],
        'adjustments' => [
            'group' => 'Transactions',
            'label' => 'Stock adjustments, stock takes and reservations',
            'hint' => 'Their quantity changes are undone.',
        ],
        'stock_movements' => [
            'group' => 'Transactions',
            'label' => 'Stock movement history',
            'hint' => 'Clears the movement log only. Stock quantities are not changed.',
        ],
        'expenses' => [
            'group' => 'Transactions',
            'label' => 'Expenses',
            'hint' => null,
        ],
        'products' => [
            'group' => 'Records',
            'label' => 'Products (parts catalogue)',
            'hint' => 'Removes products with their stock levels, references and vehicle fitments. Products used by kept transactions are skipped.',
        ],
        'contacts' => [
            'group' => 'Records',
            'label' => 'Customers and suppliers',
            'hint' => 'Contacts on kept transactions are skipped.',
        ],
        'users' => [
            'group' => 'Records',
            'label' => 'Staff user accounts',
            'hint' => 'Users who recorded kept transactions are skipped. You can create new users from this console.',
        ],
        'payment_accounts' => [
            'group' => 'Records',
            'label' => 'Payment accounts',
            'hint' => 'Accounts used by kept payments are skipped.',
        ],
        'expense_categories' => [
            'group' => 'Records',
            'label' => 'Expense categories',
            'hint' => 'Categories used by kept expenses are skipped.',
        ],
        'audit_logs' => [
            'group' => 'History',
            'label' => 'Audit log',
            'hint' => 'A record of this cleanup is always kept.',
        ],
        'email_reminders' => [
            'group' => 'History',
            'label' => 'Scheduled email reminder history',
            'hint' => null,
        ],
    ];

    private const DOCUMENT_TYPES = [
        'sales' => ['sale', 'sale_return'],
        'purchases' => ['purchase', 'purchase_return'],
        'transfers' => ['transfer'],
        'adjustments' => ['adjustment', 'stock_take', 'reservation'],
    ];

    private const CHUNK = 500;

    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * Runs the cleanup inside a transaction that is always rolled back, so the counts are exact but nothing changes.
     */
    public function preview(array $selection): array
    {
        DB::beginTransaction();

        try {
            return $this->purge($selection);
        } finally {
            DB::rollBack();
        }
    }

    public function run(array $selection): array
    {
        $summary = DB::transaction(fn (): array => $this->purge($selection));

        // Written after the purge so it survives an audit log cleanup.
        $this->auditLog->record('data.cleanup', 'Super admin permanently deleted system data.', null, [
            'categories' => array_values($selection['categories']),
            'from' => $selection['from'] ?? null,
            'to' => $selection['to'] ?? null,
            'deleted' => array_map(fn (array $row): int => $row['deleted'], $summary['categories']),
            'stock' => $summary['stock'],
        ]);

        return $summary;
    }

    public function isFullStockWipe(array $selection): bool
    {
        return blank($selection['from'] ?? null)
            && blank($selection['to'] ?? null)
            && array_diff(array_keys(self::DOCUMENT_TYPES), $selection['categories']) === [];
    }

    /**
     * @return array{categories: array<string, array{label: string, deleted: int, kept: int}>, stock: array{mode: string, rows: int}}
     */
    private function purge(array $selection): array
    {
        $categories = $selection['categories'];
        $from = filled($selection['from'] ?? null) ? Carbon::parse($selection['from'])->startOfDay() : null;
        $to = filled($selection['to'] ?? null) ? Carbon::parse($selection['to'])->endOfDay() : null;
        $fullWipe = $this->isFullStockWipe($selection);

        $results = [];
        $reversal = [];

        // Order matters: transactions first so the records they reference become free to delete.
        foreach (self::DOCUMENT_TYPES as $category => $types) {
            if (in_array($category, $categories, true)) {
                $results[$category] = $this->deleteDocuments($types, $from, $to, $reversal);
            }
        }

        $stock = ['mode' => 'unchanged', 'rows' => 0];

        if ($fullWipe) {
            $stock = ['mode' => 'zeroed', 'rows' => $this->zeroAllStock()];
        } elseif ($reversal !== []) {
            $stock = ['mode' => 'reversed', 'rows' => $this->reverseStock($reversal)];
        }

        $handlers = [
            'stock_movements' => fn (): array => $this->deleteDated('stock_movements', 'created_at', $from, $to),
            'expenses' => fn (): array => $this->deleteDated('expenses', 'expense_date', $from, $to),
            'products' => fn (): array => $this->deleteProducts($from, $to),
            'contacts' => fn (): array => $this->deleteUnreferenced('contacts', $from, $to, [['inventory_documents', 'contact_id']]),
            'users' => fn (): array => $this->deleteUsers($from, $to),
            'payment_accounts' => fn (): array => $this->deleteUnreferenced('payment_accounts', $from, $to, [['payments', 'payment_account_id']]),
            'expense_categories' => fn (): array => $this->deleteUnreferenced('expense_categories', $from, $to, [['expenses', 'expense_category_id']]),
            'audit_logs' => fn (): array => $this->deleteDated('audit_logs', 'created_at', $from, $to),
            'email_reminders' => fn (): array => $this->deleteDated('scheduled_email_reminders', 'created_at', $from, $to),
        ];

        foreach ($handlers as $category => $handler) {
            if (in_array($category, $categories, true)) {
                $results[$category] = $handler();
            }
        }

        $summary = [];

        foreach (array_keys(self::CATEGORIES) as $category) {
            if (isset($results[$category])) {
                $summary[$category] = ['label' => self::CATEGORIES[$category]['label'], ...$results[$category]];
            }
        }

        return ['categories' => $summary, 'stock' => $stock];
    }

    /**
     * @param  array<string, int>  $reversal  net quantity change per "product:site", collected for later reversal
     */
    private function deleteDocuments(array $types, ?Carbon $from, ?Carbon $to, array &$reversal): array
    {
        $deleted = 0;

        $this->dated(DB::table('inventory_documents'), 'document_date', $from, $to)
            ->whereIn('document_type', $types)
            ->select('id')
            ->chunkById(self::CHUNK, function (Collection $documents) use (&$deleted, &$reversal): void {
                $ids = $documents->pluck('id')->all();

                DB::table('stock_movements')
                    ->whereIn('inventory_document_id', $ids)
                    ->groupBy('product_id', 'site_id')
                    ->selectRaw('product_id, site_id, SUM(quantity_change) as net_change')
                    ->get()
                    ->each(function (object $row) use (&$reversal): void {
                        $key = $row->product_id.':'.$row->site_id;
                        $reversal[$key] = ($reversal[$key] ?? 0) + (int) $row->net_change;
                    });

                DB::table('stock_movements')->whereIn('inventory_document_id', $ids)->delete();
                DB::table('payments')->whereIn('inventory_document_id', $ids)->delete();
                DB::table('inventory_document_items')->whereIn('inventory_document_id', $ids)->delete();
                $deleted += DB::table('inventory_documents')->whereIn('id', $ids)->delete();
            });

        return ['deleted' => $deleted, 'kept' => 0];
    }

    /**
     * Undoes the net stock effect of deleted documents. Quantities never drop below zero, and reservations
     * are trimmed so they never exceed what is left on hand.
     */
    private function reverseStock(array $reversal): int
    {
        $updated = 0;

        foreach ($reversal as $key => $netChange) {
            if ($netChange === 0) {
                continue;
            }

            [$productId, $siteId] = array_map('intval', explode(':', $key));

            $stock = DB::table('site_stocks')
                ->where('product_id', $productId)
                ->where('site_id', $siteId)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                continue;
            }

            $onHand = max(0, (int) $stock->quantity_on_hand - $netChange);

            DB::table('site_stocks')->where('id', $stock->id)->update([
                'quantity_on_hand' => $onHand,
                'reserved_quantity' => min((int) $stock->reserved_quantity, $onHand),
                'updated_at' => now(),
            ]);
            $updated++;
        }

        return $updated;
    }

    private function zeroAllStock(): int
    {
        return DB::table('site_stocks')
            ->where(fn (Builder $query) => $query->where('quantity_on_hand', '!=', 0)->orWhere('reserved_quantity', '!=', 0))
            ->update([
                'quantity_on_hand' => 0,
                'reserved_quantity' => 0,
                'low_stock_notified_at' => null,
                'updated_at' => now(),
            ]);
    }

    private function deleteDated(string $table, string $column, ?Carbon $from, ?Carbon $to): array
    {
        if (! Schema::hasTable($table)) {
            return ['deleted' => 0, 'kept' => 0];
        }

        return ['deleted' => $this->dated(DB::table($table), $column, $from, $to)->delete(), 'kept' => 0];
    }

    /**
     * A product is in use while a kept document lists it or a kept document moved its stock.
     * Its own standalone movements (imports, opening stock) go with it.
     */
    private function deleteProducts(?Carbon $from, ?Carbon $to): array
    {
        $candidates = $this->dated(DB::table('products'), 'created_at', $from, $to);
        $total = (clone $candidates)->count();
        $deleted = 0;

        $candidates
            ->whereNotExists(fn (Builder $query) => $query->from('inventory_document_items')->whereColumn('inventory_document_items.product_id', 'products.id'))
            ->whereNotExists(fn (Builder $query) => $query->from('stock_movements')
                ->whereColumn('stock_movements.product_id', 'products.id')
                ->whereNotNull('stock_movements.inventory_document_id'))
            ->select('id')
            ->chunkById(self::CHUNK, function (Collection $products) use (&$deleted): void {
                $ids = $products->pluck('id')->all();

                DB::table('stock_movements')->whereIn('product_id', $ids)->delete();
                DB::table('site_stocks')->whereIn('product_id', $ids)->delete();
                DB::table('product_references')->whereIn('product_id', $ids)->delete();
                DB::table('product_compatibilities')->whereIn('product_id', $ids)->delete();
                $deleted += DB::table('products')->whereIn('id', $ids)->delete();
            });

        return ['deleted' => $deleted, 'kept' => $total - $deleted];
    }

    private function deleteUsers(?Carbon $from, ?Carbon $to): array
    {
        $candidates = $this->dated(DB::table('users'), 'created_at', $from, $to);
        $total = (clone $candidates)->count();
        $deleted = 0;

        foreach ([['inventory_documents', 'created_by'], ['stock_movements', 'created_by'], ['payments', 'received_by'], ['expenses', 'created_by']] as [$table, $column]) {
            $candidates->whereNotExists(fn (Builder $query) => $query->from($table)->whereColumn("{$table}.{$column}", 'users.id'));
        }

        $candidates->select('id')->chunkById(self::CHUNK, function (Collection $users) use (&$deleted): void {
            $ids = $users->pluck('id')->all();

            DB::table('user_site_access')->whereIn('user_id', $ids)->delete();
            DB::table('personal_access_tokens')->where('tokenable_type', User::class)->whereIn('tokenable_id', $ids)->delete();

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->whereIn('user_id', $ids)->delete();
            }

            $deleted += DB::table('users')->whereIn('id', $ids)->delete();
        });

        return ['deleted' => $deleted, 'kept' => $total - $deleted];
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $references  [table, column] pairs that keep a row in use
     */
    private function deleteUnreferenced(string $table, ?Carbon $from, ?Carbon $to, array $references): array
    {
        $candidates = $this->dated(DB::table($table), 'created_at', $from, $to);
        $total = (clone $candidates)->count();

        foreach ($references as [$referencingTable, $column]) {
            $candidates->whereNotExists(fn (Builder $query) => $query->from($referencingTable)->whereColumn("{$referencingTable}.{$column}", "{$table}.id"));
        }

        $deleted = $candidates->delete();

        return ['deleted' => $deleted, 'kept' => $total - $deleted];
    }

    private function dated(Builder $query, string $column, ?Carbon $from, ?Carbon $to): Builder
    {
        $column = $query->from.'.'.$column;

        return $query
            ->when($from, fn (Builder $inner) => $inner->where($column, '>=', $from))
            ->when($to, fn (Builder $inner) => $inner->where($column, '<=', $to));
    }
}
