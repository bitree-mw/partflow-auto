<?php

namespace App\Console\Commands;

use App\Models\InventoryDocument;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\StockMovement;
use App\Models\TaxProfile;
use App\Models\User;
use App\Models\UserSiteAccess;
use App\Services\InventoryDocumentService;
use App\Services\ProductService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use JsonException;
use RuntimeException;
use Throwable;

class ReplaceCatalogFromSourceManifest extends Command
{
    protected $signature = 'catalog:replace-from-source-manifest
        {manifest=database/data/source_catalog_products.json : Absolute path or project-relative manifest path}
        {--dry-run : Validate and preview the replacement without writing to the database}
        {--force : Confirm permanent removal of the current products, sites, and inventory history}';

    protected $description = 'Replace all products and sites from a validated source-workbook manifest';

    public function __construct(
        private readonly ProductService $productService,
        private readonly InventoryDocumentService $inventoryDocumentService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $manifest = $this->readManifest((string) $this->argument('manifest'));
            $validated = $this->validatedManifest($manifest);
            $preview = $this->preview($validated);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            [
                'Current products',
                'Current sites',
                'Old documents',
                'New products',
                'Store products',
                'Warehouse products',
                'Store quantity',
                'Warehouse quantity',
                'Zero-price products',
            ],
            [[
                $preview['current_products'],
                $preview['current_sites'],
                $preview['current_documents'],
                $preview['new_products'],
                $preview['store_products'],
                $preview['warehouse_products'],
                $preview['store_quantity'],
                $preview['warehouse_quantity'],
                $preview['zero_price_products'],
            ]]
        );

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. No database changes were made.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->error(
                'This command permanently removes the current products, sites, and inventory history. Re-run with --force.'
            );

            return self::FAILURE;
        }

        try {
            $result = DB::transaction(fn (): array => $this->replace($validated));
        } catch (Throwable $exception) {
            $this->error('Replacement rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Replacement complete: %d products created across %d sites.',
            $result['products_created'],
            $result['sites_created'],
        ));
        $this->line(sprintf(
            'Opening stock: %d units at Limbe Store and %d units at Limbe Warehouse.',
            $result['store_quantity'],
            $result['warehouse_quantity'],
        ));
        $this->line(sprintf(
            '%d user-site access records were recreated. Product codes use the existing system generator.',
            $result['access_records_created'],
        ));

        return self::SUCCESS;
    }

    private function readManifest(string $manifestArgument): array
    {
        $path = preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2}|\/)/', $manifestArgument) === 1
            ? $manifestArgument
            : base_path($manifestArgument);

        if (! is_file($path)) {
            throw new RuntimeException("Manifest file does not exist: {$path}");
        }

        try {
            $manifest = json_decode(
                file_get_contents($path) ?: '',
                true,
                flags: JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Manifest JSON is invalid: '.$exception->getMessage(), 0, $exception);
        }

        if (! is_array($manifest) || ($manifest['schema_version'] ?? null) !== 1) {
            throw new RuntimeException('Manifest schema_version must be 1.');
        }

        if (($manifest['import_key'] ?? null) !== 'full-source-catalog-replacement') {
            throw new RuntimeException('Manifest import_key is not recognized.');
        }

        return $manifest;
    }

    private function validatedManifest(array $manifest): array
    {
        $sites = $manifest['sites'] ?? null;
        $products = $manifest['products'] ?? null;

        if (! is_array($sites) || count($sites) !== 2) {
            throw new RuntimeException('Manifest must define exactly two sites.');
        }

        if (! is_array($products) || $products === []) {
            throw new RuntimeException('Manifest products must be a non-empty array.');
        }

        $validatedSites = [];
        $siteCodes = [];

        foreach ($sites as $index => $site) {
            $validator = Validator::make($site, [
                'name' => ['required', 'string', 'max:255'],
                'code' => ['required', 'string', 'max:255', 'regex:/^[A-Z0-9]+$/'],
                'type' => ['required', Rule::in(['shop', 'warehouse', 'branch'])],
                'location' => ['nullable', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                throw new RuntimeException(sprintf(
                    'Manifest site at index %d is invalid: %s',
                    $index,
                    $validator->errors()->first(),
                ));
            }

            $row = $validator->validated();
            $row['code'] = strtoupper($row['code']);

            if (isset($siteCodes[$row['code']])) {
                throw new RuntimeException("Manifest contains duplicate site code: {$row['code']}");
            }

            $siteCodes[$row['code']] = true;
            $validatedSites[] = $row;
        }

        $expectedSites = ['LMBST' => 'Limbe Store', 'LMBWH' => 'Limbe Warehouse'];

        foreach ($expectedSites as $code => $name) {
            $site = collect($validatedSites)->firstWhere('code', $code);

            if (! $site || $site['name'] !== $name) {
                throw new RuntimeException("Manifest must define {$name} with code {$code}.");
            }
        }

        $allowedReferenceTypes = [
            'barcode',
            'oem_number',
            'supplier_code',
            'aftermarket_code',
            'other',
        ];
        $validatedProducts = [];
        $sourceKeys = [];

        foreach ($products as $index => $product) {
            $validator = Validator::make($product, [
                'source_key' => ['required', 'string', 'max:100', 'regex:/^(?:JAN26|CPT)-[A-F0-9]{8}$/'],
                'product_name' => ['required', 'string', 'max:255'],
                'product_type' => ['required', 'array'],
                'product_type.name' => ['required', 'string', 'max:255'],
                'product_type.code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/'],
                'product_type.description' => ['nullable', 'string'],
                'source_part_number' => ['required', 'string', 'max:255'],
                'source_description' => ['required', 'string', 'max:255'],
                'source_rows' => ['required', 'string'],
                'vehicle_specific' => ['required', 'boolean'],
                'stock_site_code' => ['required', Rule::in(array_keys($siteCodes))],
                'quantity_on_hand' => ['required', 'integer', 'min:0'],
                'default_selling_price' => ['required', 'numeric', 'min:0'],
                'default_low_stock_level' => ['required', 'integer', 'min:0'],
                'unit_name' => ['required', 'string', 'max:255'],
                'pack_size' => ['required', 'numeric', 'min:0.01'],
                'is_active' => ['required', 'boolean'],
                'description' => ['required', 'string'],
                'references' => ['present', 'array'],
                'references.*.reference_type' => ['required', Rule::in($allowedReferenceTypes)],
                'references.*.reference_value' => ['required', 'string', 'max:255'],
                'references.*.is_primary' => ['required', 'boolean'],
                'references.*.notes' => ['nullable', 'string'],
            ]);

            if ($validator->fails()) {
                throw new RuntimeException(sprintf(
                    'Manifest product at index %d is invalid: %s',
                    $index,
                    $validator->errors()->first(),
                ));
            }

            $row = $validator->validated();
            $row['source_key'] = strtoupper($row['source_key']);
            $row['product_type']['code'] = strtoupper($row['product_type']['code']);
            $row['stock_site_code'] = strtoupper($row['stock_site_code']);

            if (isset($sourceKeys[$row['source_key']])) {
                throw new RuntimeException("Manifest contains duplicate source key: {$row['source_key']}");
            }

            $expectedSiteCode = $row['vehicle_specific'] ? 'LMBST' : 'LMBWH';

            if ($row['stock_site_code'] !== $expectedSiteCode) {
                throw new RuntimeException(sprintf(
                    'Manifest product %s must be routed to %s.',
                    $row['source_key'],
                    $expectedSiteCode,
                ));
            }

            $hasSourceReference = collect($row['references'])->contains(
                fn (array $reference): bool =>
                    $reference['reference_type'] === 'other' &&
                    strtoupper($reference['reference_value']) === $row['source_key']
            );

            if (! $hasSourceReference) {
                throw new RuntimeException(
                    "Manifest product {$row['source_key']} is missing its provenance reference."
                );
            }

            $sourceKeys[$row['source_key']] = true;
            $validatedProducts[] = $row;
        }

        return [
            'sites' => $validatedSites,
            'products' => $validatedProducts,
        ];
    }

    private function preview(array $manifest): array
    {
        $products = collect($manifest['products']);

        return [
            'current_products' => Product::withTrashed()->count(),
            'current_sites' => Site::withTrashed()->count(),
            'current_documents' => InventoryDocument::query()->count(),
            'new_products' => $products->count(),
            'store_products' => $products->where('stock_site_code', 'LMBST')->count(),
            'warehouse_products' => $products->where('stock_site_code', 'LMBWH')->count(),
            'store_quantity' => $products->where('stock_site_code', 'LMBST')->sum('quantity_on_hand'),
            'warehouse_quantity' => $products->where('stock_site_code', 'LMBWH')->sum('quantity_on_hand'),
            'zero_price_products' => $products->where('default_selling_price', 0)->count(),
        ];
    }

    private function replace(array $manifest): array
    {
        $user = User::query()->where('is_active', true)->orderBy('id')->first();

        if (! $user) {
            throw new RuntimeException('At least one active user is required to record opening stock.');
        }

        $accessTemplates = $this->accessTemplates();

        StockMovement::query()->delete();
        InventoryDocument::query()->delete();
        SiteStock::query()->delete();
        UserSiteAccess::withTrashed()->forceDelete();
        Product::withTrashed()->forceDelete();
        Site::withTrashed()->forceDelete();

        $sites = collect($manifest['sites'])->mapWithKeys(function (array $row): array {
            $site = Site::query()->create([
                'name' => $row['name'],
                'code' => $row['code'],
                'type' => $row['type'],
                'location' => $row['location'] ?? null,
                'phone' => null,
                'address' => null,
                'is_active' => true,
            ]);

            return [$site->code => $site];
        });

        $accessRecordsCreated = $this->recreateAccess($accessTemplates, $sites);
        $defaultTaxProfileId = TaxProfile::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->value('id');
        $typeIds = [];
        $createdRows = collect();

        foreach ($manifest['products'] as $row) {
            $typeCode = $row['product_type']['code'];

            if (! isset($typeIds[$typeCode])) {
                $productType = ProductType::withTrashed()->where('code', $typeCode)->first();

                if ($productType && $productType->name !== $row['product_type']['name']) {
                    throw new RuntimeException(sprintf(
                        'Product type code %s is already used by "%s", not "%s".',
                        $typeCode,
                        $productType->name,
                        $row['product_type']['name'],
                    ));
                }

                if (! $productType) {
                    $productType = ProductType::query()->create([
                        'name' => $row['product_type']['name'],
                        'code' => $typeCode,
                        'description' => $row['product_type']['description'] ?? null,
                        'is_active' => true,
                    ]);
                } elseif ($productType->trashed()) {
                    $productType->restore();
                    $productType->forceFill(['is_active' => true])->save();
                }

                $typeIds[$typeCode] = $productType->id;
            }

            $product = $this->productService->create([
                'product_name' => $row['product_name'],
                'car_model_id' => null,
                'product_type_id' => $typeIds[$typeCode],
                'fuel_type_id' => null,
                'brand_id' => null,
                'tax_profile_id' => $defaultTaxProfileId,
                'part_country_of_origin' => null,
                'description' => $row['description'],
                'default_selling_price' => $row['default_selling_price'],
                'default_low_stock_level' => $row['default_low_stock_level'],
                'unit_name' => $row['unit_name'],
                'pack_size' => $row['pack_size'],
                'is_active' => $row['is_active'],
                'references' => $row['references'],
                'compatibilities' => [],
            ]);

            $createdRows->push([
                'product' => $product,
                'row' => $row,
            ]);
        }

        foreach ($createdRows->groupBy(fn (array $entry): string => $entry['row']['stock_site_code']) as $siteCode => $entries) {
            $site = $sites->get($siteCode);

            if (! $site) {
                throw new RuntimeException("Opening stock site does not exist: {$siteCode}");
            }

            $positiveEntries = $entries->filter(
                fn (array $entry): bool => $entry['row']['quantity_on_hand'] > 0
            );

            if ($positiveEntries->isNotEmpty()) {
                $this->inventoryDocumentService->createStockTake([
                    'site_id' => $site->id,
                    'status' => 'approved',
                    'document_date' => now(),
                    'notes' => 'Opening stock rebuilt from the supplied source workbooks.',
                    'items' => $positiveEntries->map(fn (array $entry): array => [
                        'product_id' => $entry['product']->id,
                        'counted_quantity' => $entry['row']['quantity_on_hand'],
                        'notes' => 'Opening quantity imported from '.$entry['row']['source_rows'].'.',
                    ])->values()->all(),
                ], $user, enforceSiteAccess: false);
            }

            foreach ($entries as $entry) {
                $stock = SiteStock::query()->firstOrCreate(
                    [
                        'product_id' => $entry['product']->id,
                        'site_id' => $site->id,
                    ],
                    [
                        'quantity_on_hand' => 0,
                        'reserved_quantity' => 0,
                    ]
                );
                $stock->forceFill([
                    'low_stock_level' => $entry['row']['default_low_stock_level'],
                ])->save();
            }
        }

        return [
            'products_created' => $createdRows->count(),
            'sites_created' => $sites->count(),
            'access_records_created' => $accessRecordsCreated,
            'store_quantity' => $createdRows
                ->where('row.stock_site_code', 'LMBST')
                ->sum('row.quantity_on_hand'),
            'warehouse_quantity' => $createdRows
                ->where('row.stock_site_code', 'LMBWH')
                ->sum('row.quantity_on_hand'),
        ];
    }

    private function accessTemplates(): Collection
    {
        return User::query()
            ->get()
            ->mapWithKeys(function (User $user): array {
                $access = UserSiteAccess::withTrashed()
                    ->where('user_id', $user->id)
                    ->orderByDesc('is_default')
                    ->orderBy('id')
                    ->first();

                return [$user->id => [
                    'user_id' => $user->id,
                    'access_level' => $access?->access_level ?? 'view_only',
                    'can_view_stock' => $access?->can_view_stock ?? true,
                    'can_make_sales' => $access?->can_make_sales ?? false,
                    'can_receive_stock' => $access?->can_receive_stock ?? false,
                    'can_transfer_stock' => $access?->can_transfer_stock ?? false,
                    'can_adjust_stock' => $access?->can_adjust_stock ?? false,
                    'is_active' => $access?->is_active ?? true,
                ]];
            });
    }

    private function recreateAccess(Collection $templates, Collection $sites): int
    {
        $created = 0;

        foreach ($templates as $template) {
            foreach (['LMBST', 'LMBWH'] as $siteCode) {
                UserSiteAccess::query()->create($template + [
                    'site_id' => $sites->get($siteCode)->id,
                    'is_default' => $siteCode === 'LMBST',
                ]);
                $created++;
            }
        }

        return $created;
    }
}
