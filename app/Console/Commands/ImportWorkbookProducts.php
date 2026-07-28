<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductReference;
use App\Models\ProductType;
use App\Models\TaxProfile;
use App\Services\ProductService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use JsonException;
use RuntimeException;
use Throwable;

class ImportWorkbookProducts extends Command
{
    private int $historyPreservedCount = 0;

    protected $signature = 'products:import-workbook-manifest
        {manifest=database/data/january_2026_workbook_products.json : Absolute path or project-relative manifest path}
        {--dry-run : Validate and preview changes without writing to the database}
        {--replace-existing-import : Permanently replace the earlier JAN26-coded workbook import}';

    protected $description = 'Import normalized, deduplicated products from a workbook manifest';

    public function __construct(private readonly ProductService $productService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $manifest = $this->readManifest((string) $this->argument('manifest'));
            $products = $this->validatedProducts($manifest);
            $preview = $this->preview($products);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Manifest rows', 'Will create', 'Already present', 'Will replace', 'New product types', 'Zero-price rows'],
            [[
                count($products),
                $preview['create_count'],
                $preview['skip_count'],
                $preview['legacy_count'],
                count($preview['new_types']),
                $preview['zero_price_count'],
            ]]
        );

        if ($preview['new_types'] !== []) {
            $this->line('New product types: '.implode(', ', $preview['new_types']));
        }

        if ($preview['legacy_count'] > 0 && ! $this->option('replace-existing-import')) {
            $this->error(sprintf(
                '%d earlier JAN26-coded products exist. Re-run with --replace-existing-import.',
                $preview['legacy_count'],
            ));

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. No database changes were made.');

            return self::SUCCESS;
        }

        try {
            $result = DB::transaction(function () use ($products): array {
                $replaced = $this->option('replace-existing-import')
                    ? $this->deleteLegacyImport($products)
                    : 0;

                return ['replaced' => $replaced] + $this->import($products);
            });
        } catch (Throwable $exception) {
            $this->error('Import rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Import complete: %d earlier products replaced, %d products created, %d already present, %d product types created.',
            $result['replaced'],
            $result['created'],
            $result['skipped'],
            $result['types_created'],
        ));
        $this->line('Site stock quantities were not changed.');

        if ($this->historyPreservedCount > 0) {
            $this->line(sprintf(
                '%d product(s) were corrected in place to preserve stock and transaction history.',
                $this->historyPreservedCount,
            ));
        }

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

        if (! is_array($manifest) || ($manifest['schema_version'] ?? null) !== 2) {
            throw new RuntimeException('Manifest schema_version must be 2.');
        }

        if (($manifest['import_key'] ?? null) !== 'january-2026-workbook-products') {
            throw new RuntimeException('Manifest import_key is not recognized.');
        }

        return $manifest;
    }

    private function validatedProducts(array $manifest): array
    {
        $products = $manifest['products'] ?? null;

        if (! is_array($products) || $products === []) {
            throw new RuntimeException('Manifest products must be a non-empty array.');
        }

        $allowedReferenceTypes = [
            'barcode',
            'oem_number',
            'supplier_code',
            'aftermarket_code',
            'other',
        ];
        $validated = [];
        $sourceKeys = [];

        foreach ($products as $index => $product) {
            $validator = Validator::make($product, [
                'source_key' => ['required', 'string', 'max:100', 'regex:/^JAN26-[A-F0-9]{8}$/'],
                'product_name' => ['required', 'string', 'max:255'],
                'product_type' => ['required', 'array'],
                'product_type.name' => ['required', 'string', 'max:255'],
                'product_type.code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/'],
                'product_type.description' => ['nullable', 'string'],
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
            $sourceKey = strtoupper($row['source_key']);

            if (isset($sourceKeys[$sourceKey])) {
                throw new RuntimeException("Manifest contains duplicate source key: {$sourceKey}");
            }

            if ($row['product_name'] !== $row['product_type']['name']) {
                throw new RuntimeException(
                    "Manifest product {$sourceKey} must use the product type as its product name."
                );
            }

            $hasSourceReference = collect($row['references'])->contains(
                fn (array $reference): bool =>
                    $reference['reference_type'] === 'other' &&
                    strtoupper($reference['reference_value']) === $sourceKey
            );

            if (! $hasSourceReference) {
                throw new RuntimeException(
                    "Manifest product {$sourceKey} is missing its provenance reference."
                );
            }

            $sourceKeys[$sourceKey] = true;
            $row['source_key'] = $sourceKey;
            $row['product_type']['code'] = strtoupper($row['product_type']['code']);
            $validated[] = $row;
        }

        return $validated;
    }

    private function preview(array $products): array
    {
        $manifestSourceKeys = collect($products)->pluck('source_key');
        $existingSourceKeys = ProductReference::query()
            ->where('reference_type', 'other')
            ->whereIn('reference_value', $manifestSourceKeys)
            ->pluck('reference_value')
            ->map(fn (string $sourceKey): string => strtoupper($sourceKey))
            ->flip();
        $existingTypes = ProductType::withTrashed()
            ->whereIn('code', collect($products)->pluck('product_type.code')->unique())
            ->pluck('name', 'code');
        $newTypes = collect($products)
            ->map(fn (array $product): array => $product['product_type'])
            ->unique('code')
            ->reject(fn (array $type): bool => $existingTypes->has($type['code']))
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        return [
            'create_count' => collect($products)
                ->reject(fn (array $product): bool => $existingSourceKeys->has($product['source_key']))
                ->count(),
            'skip_count' => collect($products)
                ->filter(fn (array $product): bool => $existingSourceKeys->has($product['source_key']))
                ->count(),
            'legacy_count' => Product::query()
                ->where('product_code', 'like', '%-JAN26-%')
                ->count(),
            'new_types' => $newTypes,
            'zero_price_count' => collect($products)
                ->where('default_selling_price', 0)
                ->count(),
        ];
    }

    private function import(array $products): array
    {
        $defaultTaxProfileId = TaxProfile::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->value('id');
        $created = 0;
        $skipped = 0;
        $typesCreated = 0;
        $typeIds = [];

        foreach ($products as $row) {
            $existingProduct = Product::query()
                ->whereHas('references', function ($query) use ($row): void {
                    $query
                        ->where('reference_type', 'other')
                        ->where('reference_value', $row['source_key']);
                })
                ->first();

            if ($existingProduct) {
                $skipped++;

                continue;
            }

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
                    $productType = ProductType::create([
                        'name' => $row['product_type']['name'],
                        'code' => $typeCode,
                        'description' => $row['product_type']['description'] ?? null,
                        'is_active' => true,
                    ]);
                    $typesCreated++;
                } elseif ($productType->trashed()) {
                    $productType->restore();
                    $productType->forceFill(['is_active' => true])->save();
                }

                $typeIds[$typeCode] = $productType->id;
            }

            $this->productService->create([
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

            $created++;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'types_created' => $typesCreated,
        ];
    }

    private function deleteLegacyImport(array $products): int
    {
        $legacyQuery = Product::query()->where('product_code', 'like', '%-JAN26-%');
        $historyProducts = (clone $legacyQuery)
            ->where(function ($query): void {
                $query
                    ->whereHas('siteStocks')
                    ->orWhereHas('inventoryDocumentItems')
                    ->orWhereHas('stockMovements');
            })
            ->get();
        $manifestBySourceKey = collect($products)->keyBy('source_key');
        $historyIds = $historyProducts->pluck('id');
        $legacyProducts = (clone $legacyQuery)
            ->when(
                $historyIds->isNotEmpty(),
                fn ($query) => $query->whereNotIn('id', $historyIds)
            )
            ->get();

        $legacyProducts->each(fn (Product $product) => $product->forceDelete());

        foreach ($historyProducts as $product) {
            if (preg_match('/-JAN26-([A-F0-9]{8})$/', $product->product_code, $matches) !== 1) {
                throw new RuntimeException(
                    "Cannot derive a source key for history-bearing product {$product->product_code}."
                );
            }

            $sourceKey = 'JAN26-'.$matches[1];
            $row = $manifestBySourceKey->get($sourceKey);

            if (! $row) {
                throw new RuntimeException(
                    "History-bearing product {$product->product_code} is missing from the replacement manifest."
                );
            }

            $productType = ProductType::withTrashed()
                ->where('code', $row['product_type']['code'])
                ->firstOrFail();

            $this->productService->update($product, [
                'product_name' => $row['product_name'],
                'car_model_id' => null,
                'product_type_id' => $productType->id,
                'fuel_type_id' => null,
                'brand_id' => null,
                'part_country_of_origin' => null,
                'description' => $row['description'],
                'is_active' => true,
                'references' => $row['references'],
                'compatibilities' => [],
            ]);
        }

        $this->historyPreservedCount = $historyProducts->count();

        return $legacyProducts->count() + $historyProducts->count();
    }
}
