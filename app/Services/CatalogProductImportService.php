<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\InventoryDocument;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\TaxProfile;
use App\Models\User;
use App\Support\SpreadsheetReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Bulk stock intake from a parts sheet (CSV or Excel). Each row creates or restocks a part,
 * and the whole sheet is recorded as one completed purchase into the chosen site, paid in full.
 *
 * Sheet columns: Item Name (product type), Description (product name), Qty, Cost Price,
 * Min Selling Price, Max Selling Price, Warning Number (low stock level).
 */
class CatalogProductImportService
{
    public const MAX_ROWS = 1000;

    public const TEMPLATE_HEADERS = [
        'Item Name',
        'Description',
        'Qty',
        'Cost Price',
        'Min Selling Price',
        'Max Selling Price',
        'Warning Number',
    ];

    private const COLUMN_LABELS = [
        'product_type_name' => 'Item Name',
        'product_name' => 'Description',
        'quantity' => 'Qty',
        'unit_cost' => 'Cost Price',
        'minimum_selling_price' => 'Min Selling Price',
        'maximum_selling_price' => 'Max Selling Price',
        'low_stock_level' => 'Warning Number',
    ];

    // Row errors listed back to the user before the rest are summarised.
    private const MAX_REPORTED_ERRORS = 25;

    public function __construct(
        private readonly SpreadsheetReader $reader,
        private readonly ProductService $productService,
        private readonly InventoryDocumentService $inventoryDocumentService,
        private readonly PaymentService $paymentService,
        private readonly SiteAccessService $siteAccessService,
        private readonly AuditLogService $auditLog
    ) {}

    /**
     * Reads and checks a sheet without writing anything.
     *
     * @param  array{site_id: int, contact_id: ?int, payment_account_id: int, transaction_reference: ?string}  $context
     * @return array{rows: array<int, array>, totals: array{lines: int, units: int, new_parts: int, restocked_parts: int, new_types: int, total_cost: float}}
     */
    public function preview(string $path, string $extension, array $context, User $user): array
    {
        $this->authorizeContext($context, $user);

        try {
            $sheetRows = $this->reader->read($path, $extension);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['file' => [$exception->getMessage()]]);
        }

        $rows = $this->resolveMatches($this->validatedRows($this->mapRows($sheetRows)));

        return ['rows' => $rows, 'totals' => $this->totals($rows)];
    }

    /**
     * Creates missing product types and parts, restocks existing parts, and records one paid purchase.
     *
     * @param  array<int, array>  $rows  rows from preview(), held server-side between the preview and the confirmation
     */
    public function import(array $rows, array $context, User $user, ?string $fileName = null): InventoryDocument
    {
        $this->authorizeContext($context, $user);

        // Re-check the rows: the catalogue may have changed since the preview was built.
        $rows = $this->validatedRows(collect($rows)
            ->map(fn (array $row): array => collect($row)->only(['row_number', ...array_keys(self::COLUMN_LABELS)])->all())
            ->all());

        return DB::transaction(function () use ($rows, $context, $user, $fileName): InventoryDocument {
            $defaultTaxProfileId = TaxProfile::query()->active()->default()->value('id');
            $productTypeIds = [];
            $items = [];
            $createdParts = 0;
            $restockedParts = 0;

            foreach ($rows as $row) {
                $typeKey = $this->key($row['product_type_name']);
                $productTypeIds[$typeKey] ??= $this->productTypeFor($row['product_type_name'])->id;
                $productTypeId = $productTypeIds[$typeKey];

                $pricing = [
                    'default_selling_price' => $row['maximum_selling_price'],
                    'minimum_selling_price' => $row['minimum_selling_price'],
                    'default_low_stock_level' => $row['low_stock_level'],
                ];

                $product = $this->existingProduct($productTypeId, $row['product_name']);

                try {
                    if ($product) {
                        $product = $this->productService->update($product, [...$pricing, 'is_active' => true]);
                        $restockedParts++;
                    } else {
                        $product = $this->productService->create([
                            ...$pricing,
                            'product_name' => $row['product_name'],
                            'product_type_id' => $productTypeId,
                            'car_model_id' => null,
                            'brand_id' => null,
                            'tax_profile_id' => $defaultTaxProfileId,
                            'compatibilities' => [],
                        ]);
                        $createdParts++;
                    }
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages([
                        'file' => ["Row {$row['row_number']}: ".collect($exception->errors())->flatten()->first()],
                    ]);
                }

                $items[] = [
                    'product_id' => $product->id,
                    'quantity' => $row['quantity'],
                    'unit_cost' => $row['unit_cost'],
                ];
            }

            $source = $fileName ? "Imported from {$fileName}" : 'Imported from parts sheet';

            // Sheet costs are the final amounts paid, so no tax is added on top of them.
            $purchase = $this->inventoryDocumentService->createPurchase([
                'contact_id' => $context['contact_id'] ?? null,
                'destination_site_id' => $context['site_id'],
                'document_date' => now(),
                'status' => 'completed',
                'notes' => "{$source}: {$createdParts} new and {$restockedParts} restocked parts.",
                'items' => $items,
            ], $user, applyTax: false);

            if ((float) $purchase->total_amount > 0) {
                $account = PaymentAccount::query()->findOrFail($context['payment_account_id']);

                $this->paymentService->createForDocument($purchase, [
                    'payment_account_id' => $account->id,
                    'amount' => (float) $purchase->total_amount,
                    'payment_method' => $account->account_type,
                    'transaction_reference' => $context['transaction_reference'] ?? null,
                    'payment_date' => $purchase->document_date,
                    'notes' => 'Paid in full for imported stock.',
                ], $user);
            }

            $this->auditLog->record(
                'catalog.stock_imported',
                "{$source} into purchase {$purchase->document_number}: {$createdParts} new and {$restockedParts} restocked parts.",
                $purchase,
                [
                    'site_id' => (int) $context['site_id'],
                    'lines' => count($items),
                    'created_parts' => $createdParts,
                    'restocked_parts' => $restockedParts,
                    'total_amount' => (float) $purchase->total_amount,
                ],
                $user
            );

            return $purchase->refresh();
        });
    }

    private function authorizeContext(array $context, User $user): void
    {
        $this->siteAccessService->authorizeSite($user, (int) $context['site_id'], SiteAccessService::RECEIVE_STOCK);

        if (filled($context['contact_id'] ?? null) && ! Contact::query()->find($context['contact_id'])?->isSupplier()) {
            throw ValidationException::withMessages(['contact_id' => ['Choose a supplier.']]);
        }

        if (! PaymentAccount::query()->active()->whereKey($context['payment_account_id'])->exists()) {
            throw ValidationException::withMessages(['payment_account_id' => ['Choose an active payment account.']]);
        }
    }

    /**
     * Finds the header row and turns each later row into named fields.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapRows(array $sheetRows): array
    {
        $headerIndex = null;
        $columns = [];

        foreach ($sheetRows as $index => $cells) {
            if (collect($cells)->filter(fn ($value): bool => trim((string) $value) !== '')->isNotEmpty()) {
                $headerIndex = $index;
                $columns = $this->columnMap($cells);
                break;
            }
        }

        if ($headerIndex === null) {
            throw ValidationException::withMessages(['file' => ['The file is empty.']]);
        }

        $missing = collect(self::COLUMN_LABELS)->reject(fn (string $label, string $field): bool => isset($columns[$field]));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'file' => ['Missing column(s): '.$missing->join(', ').'. Download the template to see the expected headings.'],
            ]);
        }

        $rows = [];

        foreach (array_slice($sheetRows, $headerIndex + 1, null, true) as $index => $cells) {
            $row = ['row_number' => $index + 1];

            foreach ($columns as $field => $column) {
                $row[$field] = trim((string) ($cells[$column] ?? ''));
            }

            if (collect($row)->except('row_number')->every(fn (string $value): bool => $value === '')) {
                continue;
            }

            $rows[] = $row;
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => ['The file has headings but no part rows.']]);
        }

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages([
                'file' => ['A file can contain at most '.number_format(self::MAX_ROWS).' part rows. Split it into smaller files.'],
            ]);
        }

        return $rows;
    }

    /**
     * @return array<string, int> field name => column index
     */
    private function columnMap(array $headerCells): array
    {
        $columns = [];

        foreach ($headerCells as $column => $heading) {
            $field = $this->fieldForHeading((string) $heading);

            if ($field && ! isset($columns[$field])) {
                $columns[$field] = $column;
            }
        }

        return $columns;
    }

    private function fieldForHeading(string $heading): ?string
    {
        $normalized = Str::of($heading)->lower()->replaceMatches('/[^a-z]/', '')->toString();

        return match (true) {
            $normalized === '' => null,
            str_starts_with($normalized, 'min') => 'minimum_selling_price',
            str_starts_with($normalized, 'max') => 'maximum_selling_price',
            str_contains($normalized, 'warning') || str_contains($normalized, 'lowstock') || str_contains($normalized, 'reorder') => 'low_stock_level',
            str_contains($normalized, 'cost') => 'unit_cost',
            in_array($normalized, ['qty', 'quantity'], true) || str_starts_with($normalized, 'qty') => 'quantity',
            str_contains($normalized, 'description') || $normalized === 'productname' => 'product_name',
            str_contains($normalized, 'itemname') || in_array($normalized, ['item', 'parttype', 'producttype'], true) => 'product_type_name',
            default => null,
        };
    }

    /**
     * Normalises every row and collects all problems so the user can fix the sheet in one pass.
     */
    private function validatedRows(array $rows): array
    {
        $errors = [];
        $validated = [];
        $seen = [];

        foreach ($rows as $row) {
            $line = $row['row_number'];
            $rowErrors = [];

            $typeName = Str::of((string) $row['product_type_name'])->squish()->toString();
            $productName = Str::of((string) $row['product_name'])->squish()->toString();

            foreach (['product_type_name' => $typeName, 'product_name' => $productName] as $field => $value) {
                if ($value === '') {
                    $rowErrors[] = self::COLUMN_LABELS[$field].' is required';
                } elseif (mb_strlen($value) > 255) {
                    $rowErrors[] = self::COLUMN_LABELS[$field].' must be 255 characters or fewer';
                }
            }

            $quantity = $this->number($row['quantity']);
            $unitCost = $this->number($row['unit_cost']);
            $minimum = $this->number($row['minimum_selling_price']);
            $maximum = $this->number($row['maximum_selling_price']);
            $lowStock = $this->number($row['low_stock_level']);

            if ($quantity === null || $quantity < 1 || floor($quantity) !== $quantity) {
                $rowErrors[] = 'Qty must be a whole number of at least 1';
            }

            if ($lowStock === null || $lowStock < 0 || floor($lowStock) !== $lowStock) {
                $rowErrors[] = 'Warning Number must be a whole number of 0 or more';
            }

            foreach (['unit_cost' => $unitCost, 'minimum_selling_price' => $minimum, 'maximum_selling_price' => $maximum] as $field => $value) {
                if ($value === null || $value < 0) {
                    $rowErrors[] = self::COLUMN_LABELS[$field].' must be a number of 0 or more';
                }
            }

            if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
                $rowErrors[] = 'Min Selling Price cannot be more than Max Selling Price';
            }

            $duplicateKey = $this->key($typeName).'|'.$this->key($productName);

            if ($typeName !== '' && $productName !== '' && isset($seen[$duplicateKey])) {
                $rowErrors[] = "the same part is already on row {$seen[$duplicateKey]}; combine them into one row";
            }

            $seen[$duplicateKey] ??= $line;

            if ($rowErrors !== []) {
                $errors[] = "Row {$line}: ".implode('; ', $rowErrors).'.';

                continue;
            }

            $validated[] = [
                'row_number' => $line,
                'product_type_name' => $typeName,
                'product_name' => $productName,
                'quantity' => (int) $quantity,
                'unit_cost' => round($unitCost, 2),
                'minimum_selling_price' => round($minimum, 2),
                'maximum_selling_price' => round($maximum, 2),
                'low_stock_level' => (int) $lowStock,
            ];
        }

        if ($errors !== []) {
            $reported = array_slice($errors, 0, self::MAX_REPORTED_ERRORS);
            $remaining = count($errors) - count($reported);

            if ($remaining > 0) {
                $reported[] = "…and {$remaining} more row(s) with problems.";
            }

            throw ValidationException::withMessages(['file' => $reported]);
        }

        return $validated;
    }

    /**
     * Accepts plain and formatted numbers such as "12500", "12,500.00" or "MWK 12,500".
     */
    private function number(mixed $value): ?float
    {
        $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', trim((string) $value)));

        if ($clean === '' || $clean === null || ! is_numeric($clean)) {
            return null;
        }

        return round((float) $clean, 4);
    }

    /**
     * Marks each row as a new or restocked part and flags product types that will be created.
     */
    private function resolveMatches(array $rows): array
    {
        $types = [];

        return collect($rows)->map(function (array $row) use (&$types): array {
            $typeKey = $this->key($row['product_type_name']);
            $types[$typeKey] ??= $this->findProductType($row['product_type_name']);
            $productType = $types[$typeKey];
            $existing = $productType && ! $productType->trashed()
                ? $this->existingProduct($productType->id, $row['product_name'])
                : null;

            return [
                ...$row,
                'line_total' => round($row['quantity'] * $row['unit_cost'], 2),
                'is_new_type' => ! $productType || $productType->trashed(),
                'existing_product_id' => $existing?->id,
                'existing_product_code' => $existing?->product_code,
            ];
        })->all();
    }

    private function totals(array $rows): array
    {
        $rows = collect($rows);

        return [
            'lines' => $rows->count(),
            'units' => (int) $rows->sum('quantity'),
            'new_parts' => $rows->whereNull('existing_product_id')->count(),
            'restocked_parts' => $rows->whereNotNull('existing_product_id')->count(),
            'new_types' => $rows->where('is_new_type', true)->unique(fn (array $row): string => $this->key($row['product_type_name']))->count(),
            'total_cost' => round((float) $rows->sum('line_total'), 2),
        ];
    }

    private function findProductType(string $name): ?ProductType
    {
        return ProductType::withTrashed()
            ->whereRaw('LOWER(name) = ?', [$this->key($name)])
            ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('id')
            ->first();
    }

    private function productTypeFor(string $name): ProductType
    {
        $productType = $this->findProductType($name);

        if ($productType?->trashed()) {
            $productType->restore();
        }

        if ($productType) {
            if (! $productType->is_active) {
                $productType->forceFill(['is_active' => true])->save();
            }

            return $productType;
        }

        return ProductType::query()->create([
            'name' => $name,
            'code' => $this->uniqueProductTypeCode($name),
            'description' => 'Created by parts sheet import.',
            'is_active' => true,
        ]);
    }

    private function uniqueProductTypeCode(string $name): string
    {
        $base = Str::of($name)->ascii()->upper()->replaceMatches('/[^A-Z0-9]/', '')->substr(0, 20)->toString();
        $base = $base !== '' ? $base : 'TYPE';
        $candidate = $base;
        $suffix = 2;

        while (ProductType::withTrashed()->where('code', $candidate)->exists()) {
            $candidate = substr($base, 0, 20 - strlen((string) $suffix)).$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function existingProduct(int $productTypeId, string $productName): ?Product
    {
        return Product::query()
            ->where('product_type_id', $productTypeId)
            ->whereRaw('LOWER(product_name) = ?', [$this->key($productName)])
            ->orderBy('id')
            ->first();
    }

    private function key(string $value): string
    {
        return mb_strtolower(Str::of($value)->squish()->toString());
    }
}
