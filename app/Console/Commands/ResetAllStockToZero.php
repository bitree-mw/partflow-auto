<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\User;
use App\Services\InventoryDocumentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ResetAllStockToZero extends Command
{
    protected $signature = 'stock:reset-all-to-zero
        {--dry-run : Preview the reset without changing stock}
        {--force : Confirm that all stock quantities and low-stock levels should be reset}';

    protected $description = 'Reset every site stock quantity, reservation, and low-stock level to zero';

    public function __construct(
        private readonly InventoryDocumentService $inventoryDocumentService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $preview = [
            'products' => Product::query()->count(),
            'stock_rows' => SiteStock::query()->count(),
            'units' => SiteStock::query()->sum('quantity_on_hand'),
            'reserved' => SiteStock::query()->sum('reserved_quantity'),
            'product_low_levels' => Product::query()->where('default_low_stock_level', '>', 0)->count(),
            'site_low_levels' => SiteStock::query()->where('low_stock_level', '>', 0)->count(),
        ];

        $this->table(
            [
                'Products',
                'Stock rows',
                'Units to remove',
                'Reserved units to clear',
                'Product low levels',
                'Site low levels',
            ],
            [[
                $preview['products'],
                $preview['stock_rows'],
                $preview['units'],
                $preview['reserved'],
                $preview['product_low_levels'],
                $preview['site_low_levels'],
            ]]
        );

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. No stock values were changed.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->error('Re-run with --force to reset every stock quantity and low-stock level to zero.');

            return self::FAILURE;
        }

        try {
            $documents = DB::transaction(fn (): int => $this->resetStock());
        } catch (Throwable $exception) {
            $this->error('Stock reset rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('All on-hand, reserved, and low-stock quantities are now zero.');
        $this->line(sprintf(
            '%d approved stock-take document(s) record the reset. Product selling prices were not changed.',
            $documents,
        ));

        return self::SUCCESS;
    }

    private function resetStock(): int
    {
        $user = User::query()->where('is_active', true)->orderBy('id')->first();

        if (! $user) {
            throw new RuntimeException('At least one active user is required to record the stock reset.');
        }

        $documents = 0;

        SiteStock::query()
            ->where('reserved_quantity', '!=', 0)
            ->update([
                'reserved_quantity' => 0,
                'updated_at' => now(),
            ]);

        Site::query()
            ->whereHas('siteStocks', fn ($query) => $query->where('quantity_on_hand', '>', 0))
            ->orderBy('id')
            ->each(function (Site $site) use ($user, &$documents): void {
                $items = $site->siteStocks()
                    ->where('quantity_on_hand', '>', 0)
                    ->orderBy('product_id')
                    ->get()
                    ->map(fn (SiteStock $stock): array => [
                        'product_id' => $stock->product_id,
                        'counted_quantity' => 0,
                        'notes' => 'Full stock reset requested by the business owner.',
                    ])
                    ->all();

                $this->inventoryDocumentService->createStockTake([
                    'site_id' => $site->id,
                    'status' => 'approved',
                    'document_date' => now(),
                    'notes' => 'All stock quantities reset to zero by owner request.',
                    'items' => $items,
                ], $user);
                $documents++;
            });

        SiteStock::query()->update([
            'quantity_on_hand' => 0,
            'reserved_quantity' => 0,
            'low_stock_level' => 0,
            'updated_at' => now(),
        ]);
        Product::query()->update([
            'default_low_stock_level' => 0,
            'updated_at' => now(),
        ]);

        return $documents;
    }
}
