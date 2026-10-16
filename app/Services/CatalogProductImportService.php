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

    public const MAX_FILE_KB = 5120;

    // Used when the Warning Number column is missing or a row leaves it blank.
    public const DEFAULT_LOW_STOCK_LEVEL = 10;

    private const OPTIONAL_COLUMNS = ['low_stock_level'];

    // Share of words two part names must have in common to be offered as a possible match.
    private const SIMILARITY_THRESHOLD = 0.5;

    private const MAX_SIMILAR_PARTS = 3;

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
     * Turns the user's per-row choices for similar parts into merge targets, accepting only parts that were
     * offered for that row. Every row with similar parts needs a choice. Rows that end up on the same part
     * are combined by import().
     *
     * @param  array<int|string, string|null>  $decisions  row number => "new" or an offered product id
     * @return array<int, int> row number => existing product id to add the stock to
     */
    public function resolveDecisions(array $rows, array $decisions): array
    {
        $errors = [];
        $merges = [];

        foreach ($rows as $row) {
            $line = (int) $row['row_number'];
            $offered = array_map('intval', array_column($row['similar'] ?? [], 'id'));

            if ($offered === []) {
                continue;
            }

            $choice = (string) ($decisions[$line] ?? '');

            if ($choice === 'new') {
                continue;
            }

            if ($choice === '' || ! ctype_digit($choice) || ! in_array((int) $choice, $offered, true)) {
                $errors[] = "Row {$line}: choose whether to add it to one of the similar parts or create a new part.";

                continue;
            }

            $merges[$line] = (int) $choice;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['merge' => $errors]);
        }

        return $merges;
    }

    /**
     * Creates missing product types and parts, restocks existing parts, and records one paid purchase.
     *
     * Rows for the same part (repeated in the sheet, or sent to one existing part) are combined: quantities add
     * up, rows with the same cost share one purchase line, different costs stay as separate lines so the total
     * is exact, and the last row's selling prices and Warning Number win.
     *
     * @param  array<int, array>  $rows  rows from preview(), held server-side between the preview and the confirmation
     * @param  array<int, int>  $merges  row number => existing product id chosen on the review page (see resolveDecisions())
     */
    public function import(array $rows, array $context, User $user, ?string $fileName = null, array $merges = []): InventoryDocument
    {
        $this->authorizeContext($context, $user);

        // Re-check the rows: the catalogue may have changed since the preview was built. A defaulted
        // Warning Number goes back in blank so a combined row never overrides a real number with it.
        $rows = $this->validatedRows(collect($rows)
            ->map(fn (array $row): array => [
                ...collect($row)->only(['row_number', ...array_keys(self::COLUMN_LABELS)])->all(),
                ...(($row['low_stock_defaulted'] ?? false) ? ['low_stock_level' => ''] : []),
            ])
            ->all());

        return DB::transaction(function () use ($rows, $context, $user, $fileName, $merges): InventoryDocument {
            $defaultTaxProfileId = TaxProfile::query()->active()->default()->value('id');
            $productTypeIds = [];
            $productsByRow = [];
            $lineByProductAndCost = [];
            $items = [];
            $createdParts = 0;
            $restockedParts = 0;
            $mergedParts = 0;
            $combinedRows = 0;
            $originalPrices = [];
            $createdIds = [];

            // Resolve every required type before inserting any parts. Rows explicitly merged into
            // another part keep that part's type and do not need a new, unused type.
            foreach ($rows as $row) {
                if ($row['combined_into'] === null && ! isset($merges[$row['row_number']])) {
                    $typeKey = $this->key($row['product_type_name']);
                    $productTypeIds[$typeKey] ??= $this->productTypeFor($row['product_type_name'])->id;
                }
            }

            foreach ($rows as $row) {
                $pricing = [
                    'default_selling_price' => $row['maximum_selling_price'],
                    'minimum_selling_price' => $row['minimum_selling_price'],
                    'default_low_stock_level' => $row['low_stock_level'],
                ];
                $productTypeId = null;

                if ($row['combined_into'] !== null) {
                    // A repeat of an earlier row: same part, so only its prices may change.
                    $product = $productsByRow[$row['combined_into']];
                    $combinedRows++;
                } elseif (isset($merges[$row['row_number']])) {
                    // The user chose an existing similar part: its own name and type are kept.
                    $product = Product::query()->lockForUpdate()->find($merges[$row['row_number']]);

                    if (! $product) {
                        throw ValidationException::withMessages([
                            'file' => ["Row {$row['row_number']}: the part chosen to add this stock to no longer exists. Upload the sheet again."],
                        ]);
                    }

                    $mergedParts++;
                } else {
                    $typeKey = $this->key($row['product_type_name']);
                    $productTypeId = $productTypeIds[$typeKey];
                    $product = $this->existingProduct($productTypeId, $row['product_name'], lock: true);
                }

                $alreadyInPurchase = $product && isset($lineByProductAndCost[$product->id]);

                if ($row['low_stock_defaulted'] && $alreadyInPurchase) {
                    unset($pricing['default_low_stock_level']);
                }

                try {
                    if ($product) {
                        if (! isset($createdIds[$product->id])) {
                            $originalPrices[$product->id] ??= $product->only(['default_purchase_price', 'minimum_selling_price', 'default_selling_price']);
                        }
                        $product = $this->productService->update($product, [...$pricing, 'is_active' => true], loadRelations: false);

                        // Repeats and similar-part choices were counted above; an exact match that another
                        // row already brought into this purchase is a combination, not another restock.
                        if ($row['combined_into'] === null && ! isset($merges[$row['row_number']])) {
                            $alreadyInPurchase ? $combinedRows++ : $restockedParts++;
                        }
                    } else {
                        $product = $this->productService->create([
                            ...$pricing,
                            'product_name' => $row['product_name'],
                            'product_type_id' => $productTypeId,
                            'car_model_id' => null,
                            'brand_id' => null,
                            'tax_profile_id' => $defaultTaxProfileId,
                            'compatibilities' => [],
                        ], loadRelations: false);
                        $createdParts++;
                        $createdIds[$product->id] = true;
                    }
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages([
                        'file' => ["Row {$row['row_number']}: ".collect($exception->errors())->flatten()->first()],
                    ]);
                }

                $productsByRow[$row['row_number']] = $product;
                $costKey = number_format($row['unit_cost'], 2, '.', '');
                $line = $lineByProductAndCost[$product->id][$costKey] ?? null;

                if ($line === null) {
                    $lineByProductAndCost[$product->id][$costKey] = count($items);
                    $items[] = [
                        'product_id' => $product->id,
                        'quantity' => $row['quantity'],
                        'unit_cost' => $row['unit_cost'],
                    ];
                } else {
                    $items[$line]['quantity'] += $row['quantity'];
                }
            }

            $source = $fileName ? "Imported from {$fileName}" : 'Imported from parts sheet';
            $mergedNote = ($mergedParts > 0 ? " ({$mergedParts} added to similar existing parts)" : '')
                .($combinedRows > 0 ? " ({$combinedRows} repeated rows combined)" : '');

            // Sheet costs are the final amounts paid, so no tax is added on top of them.
            $purchase = $this->inventoryDocumentService->createPurchase([
                'contact_id' => $context['contact_id'] ?? null,
                'destination_site_id' => $context['site_id'],
                'document_date' => now(),
                'status' => 'completed',
                'notes' => "{$source}: {$createdParts} new and {$restockedParts} restocked parts{$mergedNote}.",
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

            $priceChanges = [];
            foreach (Product::query()->whereKey(array_keys($originalPrices))->get() as $product) {
                $changes = $this->auditLog->diff($originalPrices[$product->id], $product->only(array_keys($originalPrices[$product->id])));
                if ($changes !== []) {
                    $priceChanges[$product->id] = ['code' => $product->product_code, 'changes' => $changes];
                }
            }

            $this->auditLog->record(
                'catalog.stock_imported',
                "{$source} into purchase {$purchase->document_number}: {$createdParts} new and {$restockedParts} restocked parts{$mergedNote}.",
                $purchase,
                [
                    'site_id' => (int) $context['site_id'],
                    'lines' => count($items),
                    'created_parts' => $createdParts,
                    'restocked_parts' => $restockedParts,
                    'merged_into_similar_parts' => $mergedParts,
                    'total_amount' => (float) $purchase->total_amount,
                    'price_changes' => $priceChanges,
                ],
                $user
            );

            return $purchase->refresh()->setAttribute('import_price_change_count', count($priceChanges));
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

        $missing = collect(self::COLUMN_LABELS)
            ->except(self::OPTIONAL_COLUMNS)
            ->reject(fn (string $label, string $field): bool => isset($columns[$field]));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'file' => ['Missing column(s): '.$missing->join(', ').'. Download the template to see the expected headings.'],
            ]);
        }

        $rows = [];

        foreach (array_slice($sheetRows, $headerIndex + 1, null, true) as $index => $cells) {
            $row = ['row_number' => $index + 1, ...array_fill_keys(self::OPTIONAL_COLUMNS, '')];

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
            $lowStockDefaulted = trim((string) ($row['low_stock_level'] ?? '')) === '';
            $lowStock = $lowStockDefaulted ? (float) self::DEFAULT_LOW_STOCK_LEVEL : $this->number($row['low_stock_level']);

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

            // Repeated parts are combined into the first row's part (see import()), not rejected.
            $duplicateKey = $this->key($typeName).'|'.$this->key($productName);
            $combinedInto = $seen[$duplicateKey] ?? null;
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
                'low_stock_defaulted' => $lowStockDefaulted,
                'combined_into' => $combinedInto,
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
                'price_changes' => $existing ? $this->priceChanges($existing, $row) : [],
            ];
        })->pipe(fn ($resolved) => $this->withSimilarParts($resolved->all()));
    }

    /**
     * For rows without an exact match (same type and name), lists existing parts whose names share most of
     * their words, so the user can add the stock to one of them instead of creating a near-duplicate.
     */
    private function withSimilarParts(array $rows): array
    {
        if (collect($rows)->whereNull('existing_product_id')->isEmpty()) {
            return $rows;
        }

        $catalogue = Product::query()
            ->join('product_types', 'product_types.id', '=', 'products.product_type_id')
            ->orderBy('products.id')
            ->get(['products.id', 'products.product_code', 'products.product_name', 'products.default_purchase_price', 'products.minimum_selling_price', 'products.default_selling_price', 'product_types.name as type_name']);
        $tokenCounts = [];
        $index = [];
        $byId = [];
        $names = [];

        foreach ($catalogue as $product) {
            $tokens = $this->nameTokens($product->product_name);
            $tokenCounts[$product->id] = count($tokens);
            $byId[$product->id] = $product;
            $names[$product->id] = implode(' ', $tokens);

            foreach ($tokens as $token) {
                $index[$token][] = $product->id;
            }
        }

        return array_map(function (array $row) use ($index, $tokenCounts, $byId, $names): array {
            $row['similar'] = [];

            // Repeated rows follow the first row's part, so they are never asked about separately.
            if ($row['existing_product_id'] || ($row['combined_into'] ?? null) !== null) {
                return $row;
            }

            $tokens = $this->nameTokens($row['product_name']);
            $shared = [];

            foreach ($tokens as $token) {
                foreach ($index[$token] ?? [] as $productId) {
                    $shared[$productId] = ($shared[$productId] ?? 0) + 1;
                }
            }

            $scores = [];

            foreach ($shared as $productId => $common) {
                $score = $common / (count($tokens) + $tokenCounts[$productId] - $common);

                if ($score >= self::SIMILARITY_THRESHOLD) {
                    $scores[$productId] = $score;
                }
            }

            // Edit distance catches spelling mistakes even when no complete words match.
            // Suggestions still require an explicit choice; never silently merge vehicle parts.
            $name = implode(' ', $tokens);
            foreach ($names as $productId => $candidate) {
                $length = max(strlen($name), strlen($candidate));
                if ($length < 4 || abs(strlen($name) - strlen($candidate)) > $length * 0.2) {
                    continue;
                }
                $score = 1 - levenshtein($name, $candidate) / $length;
                if ($score >= 0.8) {
                    $scores[$productId] = max($scores[$productId] ?? 0, $score);
                }
            }

            arsort($scores);

            foreach (array_slice($scores, 0, self::MAX_SIMILAR_PARTS, true) as $productId => $score) {
                $row['similar'][] = [
                    'id' => $productId,
                    'code' => $byId[$productId]->product_code,
                    'name' => $byId[$productId]->product_name,
                    'type' => $byId[$productId]->type_name,
                    'score' => (int) round($score * 100),
                    'price_changes' => $this->priceChanges($byId[$productId], $row),
                ];
            }

            return $row;
        }, $rows);
    }

    /**
     * @return array<int, string> distinct lower-case words and numbers of a part name
     */
    private function nameTokens(string $name): array
    {
        return array_values(array_unique(array_filter(
            preg_split('/[^\pL\pN]+/u', mb_strtolower($name)) ?: [],
            fn (string $token): bool => mb_strlen($token) >= 2 || ctype_digit($token)
        )));
    }

    /**
     * Rows that repeat an earlier row's part, grouped under that first row, for the review page.
     *
     * @return array<int, array{first_row: int, type: string, name: string, rows: array<int, array>, total_quantity: int, mixed_costs: bool, mixed_prices: bool}>
     */
    public function duplicateGroups(array $rows): array
    {
        $byRow = collect($rows)->keyBy('row_number');

        return collect($rows)
            ->whereNotNull('combined_into')
            ->groupBy('combined_into')
            ->map(function ($repeats, $firstRow) use ($byRow): array {
                $group = collect([$byRow[$firstRow], ...$repeats->all()]);

                return [
                    'first_row' => (int) $firstRow,
                    'type' => $byRow[$firstRow]['product_type_name'],
                    'name' => $byRow[$firstRow]['product_name'],
                    'rows' => $group->map(fn (array $row): array => collect($row)
                        ->only(['row_number', 'quantity', 'unit_cost', 'minimum_selling_price', 'maximum_selling_price'])
                        ->all())->values()->all(),
                    'total_quantity' => (int) $group->sum('quantity'),
                    'mixed_costs' => $group->pluck('unit_cost')->unique()->count() > 1,
                    'mixed_prices' => $group->map(fn (array $row): string => $row['minimum_selling_price'].'|'.$row['maximum_selling_price'])->unique()->count() > 1,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Drops repeated rows from a held preview, keeping each part's first row. Only the given groups
     * (by first row number) are touched, or every group when none are given.
     *
     * @param  array<int, int>|null  $firstRows
     * @return array{rows: array<int, array>, totals: array, removed: int}
     */
    public function removeDuplicateRows(array $rows, ?array $firstRows = null): array
    {
        $kept = collect($rows)->reject(fn (array $row): bool => ($row['combined_into'] ?? null) !== null
            && ($firstRows === null || in_array((int) $row['combined_into'], $firstRows, true)))
            ->values()
            ->all();

        return ['rows' => $kept, 'totals' => $this->totals($kept), 'removed' => count($rows) - count($kept)];
    }

    /**
     * @return array<int, string> existing part type names, for mapping new types on the review page
     */
    public function partTypeNames(): array
    {
        return ProductType::query()->orderBy('name')->pluck('name')->all();
    }

    /**
     * Part types in the sheet that do not exist yet, each with up to 3 existing types whose names look alike.
     *
     * @return array<int, array{first_row: int, name: string, rows: int, suggestions: array<int, string>}>
     */
    public function newTypeGroups(array $rows): array
    {
        $groups = collect($rows)->where('is_new_type', true)->groupBy(fn (array $row): string => $this->key($row['product_type_name']));

        if ($groups->isEmpty()) {
            return [];
        }

        $existing = ProductType::query()->orderBy('name')->pluck('name');

        return $groups->map(function ($typeRows) use ($existing): array {
            $name = (string) $typeRows->first()['product_type_name'];
            $tokens = $this->nameTokens($name);
            $squashed = preg_replace('/[^\pL\pN]/u', '', mb_strtolower($name));

            $suggestions = $existing
                ->map(function (string $candidate) use ($tokens, $squashed): array {
                    $candidateTokens = $this->nameTokens($candidate);
                    $common = count(array_intersect($tokens, $candidateTokens));
                    $union = count(array_unique([...$tokens, ...$candidateTokens]));
                    $candidateSquashed = preg_replace('/[^\pL\pN]/u', '', mb_strtolower($candidate));
                    // "Headlamp" and "Head Lamp" only match once spaces are ignored.
                    $score = $candidateSquashed === $squashed ? 1.0 : ($union > 0 ? $common / $union : 0.0);

                    if ($score < self::SIMILARITY_THRESHOLD && $squashed !== '' && $candidateSquashed !== '') {
                        similar_text($squashed, $candidateSquashed, $percent);
                        $score = max($score, $percent >= 80 ? $percent / 100 : 0.0);
                    }

                    return ['name' => $candidate, 'score' => $score];
                })
                ->filter(fn (array $candidate): bool => $candidate['score'] >= self::SIMILARITY_THRESHOLD)
                ->sortByDesc('score')
                ->take(self::MAX_SIMILAR_PARTS)
                ->pluck('name')
                ->values()
                ->all();

            return [
                'first_row' => (int) $typeRows->first()['row_number'],
                'name' => $name,
                'rows' => $typeRows->count(),
                'suggestions' => $suggestions,
            ];
        })->values()->all();
    }

    /**
     * Points new part types from the sheet at existing types, then rebuilds the held preview so exact matches,
     * repeated rows, similar parts and totals reflect the mapping. A blank choice keeps the type as new.
     *
     * @param  array<int|string, string|null>  $choices  first row number of a new type => existing type name
     * @return array{rows: array<int, array>, totals: array, mapped: int}
     */
    public function remapTypes(array $rows, array $choices): array
    {
        $groups = collect($this->newTypeGroups($rows))->keyBy('first_row');
        $renames = [];
        $errors = [];

        foreach ($choices as $firstRow => $choice) {
            $choice = Str::of((string) $choice)->squish()->toString();
            $group = $groups->get((int) $firstRow);

            if ($choice === '' || ! $group) {
                continue;
            }

            $type = ProductType::query()->whereRaw('LOWER(name) = ?', [$this->key($choice)])->orderBy('id')->first();

            if (! $type) {
                $errors[] = "\"{$choice}\" is not an existing part type. Pick one from the list, or leave it blank to create \"{$group['name']}\".";

                continue;
            }

            $renames[$this->key($group['name'])] = $type->name;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['types' => $errors]);
        }

        if ($renames === []) {
            return ['rows' => $rows, 'totals' => $this->totals($rows), 'mapped' => 0];
        }

        $rebuilt = $this->rebuild(collect($rows)->map(fn (array $row): array => [
            ...$row,
            'product_type_name' => $renames[$this->key($row['product_type_name'])] ?? $row['product_type_name'],
        ])->all());

        return ['rows' => $rebuilt, 'totals' => $this->totals($rebuilt), 'mapped' => count($renames)];
    }

    /**
     * Drops chosen rows from a held preview and rebuilds it, so if a part's first row goes, its next
     * repeat takes over. At least one row must remain; cancelling the import is the way to drop them all.
     *
     * @param  array<int, int>  $rowNumbers
     * @return array{rows: array<int, array>, totals: array, removed: int}
     */
    public function removeRows(array $rows, array $rowNumbers): array
    {
        $kept = array_values(array_filter($rows, fn (array $row): bool => ! in_array((int) $row['row_number'], $rowNumbers, true)));

        if ($kept === []) {
            throw ValidationException::withMessages(['rows' => ['At least one row must stay in the import. Use Cancel to discard the whole sheet.']]);
        }

        $removed = count($rows) - count($kept);
        $rebuilt = $removed > 0 ? $this->rebuild($kept) : $rows;

        return ['rows' => $rebuilt, 'totals' => $this->totals($rebuilt), 'removed' => $removed];
    }

    /**
     * Runs held preview rows back through the same checks as an upload (a defaulted Warning Number goes back
     * in blank), so exact matches, repeated rows, similar parts and new types are worked out again.
     */
    private function rebuild(array $rows): array
    {
        $raw = collect($rows)->map(fn (array $row): array => [
            ...collect($row)->only(['row_number', ...array_keys(self::COLUMN_LABELS)])->all(),
            ...(($row['low_stock_defaulted'] ?? false) ? ['low_stock_level' => ''] : []),
        ])->all();

        return $this->resolveMatches($this->validatedRows($raw));
    }

    /**
     * Money view of the previewed sheet. Profit percentages are on the purchase cost (mark-up).
     *
     * @return array{purchase_total: float, min_value: float, max_value: float, min_profit: float, max_profit: float, min_profit_percent: ?float, max_profit_percent: ?float}
     */
    public function financials(array $rows): array
    {
        $rows = collect($rows);
        $purchase = round((float) $rows->sum(fn (array $row): float => $row['quantity'] * $row['unit_cost']), 2);
        $minValue = round((float) $rows->sum(fn (array $row): float => $row['quantity'] * $row['minimum_selling_price']), 2);
        $maxValue = round((float) $rows->sum(fn (array $row): float => $row['quantity'] * $row['maximum_selling_price']), 2);
        $percent = fn (float $profit): ?float => $purchase > 0 ? round($profit / $purchase * 100, 1) : null;

        return [
            'purchase_total' => $purchase,
            'min_value' => $minValue,
            'max_value' => $maxValue,
            'min_profit' => round($minValue - $purchase, 2),
            'max_profit' => round($maxValue - $purchase, 2),
            'min_profit_percent' => $percent($minValue - $purchase),
            'max_profit_percent' => $percent($maxValue - $purchase),
        ];
    }

    /**
     * Groups previewed rows by part (product) type: what each type will receive if the import is confirmed.
     *
     * @return array<int, array{name: string, is_new_type: bool, lines: int, new_parts: int, restocked_parts: int, similar_parts: int, units: int, total_cost: float, min_profit: float, max_profit: float}>
     */
    public function typeSummary(array $rows): array
    {
        return collect($rows)
            ->groupBy(fn (array $row): string => $this->key($row['product_type_name']))
            ->map(function ($typeRows): array {
                $money = $this->financials($typeRows->all());

                return [
                    'name' => Str::of((string) $typeRows->first()['product_type_name'])->squish()->toString(),
                    'is_new_type' => (bool) $typeRows->first()['is_new_type'],
                    'lines' => $typeRows->count(),
                    'new_parts' => $typeRows->whereNull('combined_into')->whereNull('existing_product_id')->count(),
                    'restocked_parts' => $typeRows->whereNull('combined_into')->whereNotNull('existing_product_id')->count(),
                    'combined_rows' => $typeRows->whereNotNull('combined_into')->count(),
                    'similar_parts' => $typeRows->filter(fn (array $row): bool => ($row['similar'] ?? []) !== [])->count(),
                    'units' => (int) $typeRows->sum('quantity'),
                    'total_cost' => $money['purchase_total'],
                    'min_profit' => $money['min_profit'],
                    'max_profit' => $money['max_profit'],
                ];
            })
            ->sortByDesc('total_cost')
            ->values()
            ->all();
    }

    private function totals(array $rows): array
    {
        $rows = collect($rows);

        return [
            'lines' => $rows->count(),
            'units' => (int) $rows->sum('quantity'),
            'new_parts' => $rows->whereNull('combined_into')->whereNull('existing_product_id')->count(),
            'restocked_parts' => $rows->whereNull('combined_into')->whereNotNull('existing_product_id')->count(),
            'combined_rows' => $rows->whereNotNull('combined_into')->count(),
            'similar_parts' => $rows->filter(fn (array $row): bool => ($row['similar'] ?? []) !== [])->count(),
            'new_types' => $rows->where('is_new_type', true)->unique(fn (array $row): string => $this->key($row['product_type_name']))->count(),
            'default_low_stock' => $rows->where('low_stock_defaulted', true)->count(),
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

    private function existingProduct(int $productTypeId, string $productName, bool $lock = false): ?Product
    {
        return Product::query()
            ->where('product_type_id', $productTypeId)
            ->whereRaw('LOWER(product_name) = ?', [$this->key($productName)])
            ->orderBy('id')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    private function priceChanges(Product $product, array $row): array
    {
        $changes = [];
        foreach ([
            'Cost price' => ['default_purchase_price', 'unit_cost'],
            'Minimum selling price' => ['minimum_selling_price', 'minimum_selling_price'],
            'Maximum selling price' => ['default_selling_price', 'maximum_selling_price'],
        ] as $label => [$field, $column]) {
            $before = number_format((float) $product->$field, 2, '.', '');
            $after = number_format((float) $row[$column], 2, '.', '');
            if ($before !== $after) {
                $changes[] = "{$label}: {$before} → {$after}";
            }
        }

        return $changes;
    }

    private function key(string $value): string
    {
        return mb_strtolower(Str::of($value)->squish()->toString());
    }
}
