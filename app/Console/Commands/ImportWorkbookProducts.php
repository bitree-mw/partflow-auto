<?php

namespace App\Console\Commands;

use App\Models\Product;
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
    protected $signature = 'products:import-workbook-manifest
        {manifest=database/data/january_2026_workbook_products.json : Absolute path or project-relative manifest path}
        {--dry-run : Validate and preview changes without writing to the database}';

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
            ['Manifest rows', 'Will create', 'Already present', 'New product types', 'Zero-price rows'],
            [[
                count($products),
                $preview['create_count'],
                $preview['skip_count'],
                count($preview['new_types']),
                $preview['zero_price_count'],
            ]]
        );

        if ($preview['new_types'] !== []) {
            $this->line('New product types: '.implode(', ', $preview['new_types']));
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. No database changes were made.');

            return self::SUCCESS;
        }

        try {
            $result = DB::transaction(fn (): array => $this->import($products));
        } catch (Throwable $exception) {
            $this->error('Import rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Import complete: %d products created, %d already present, %d product types created.',
            $result['created'],
            $result['skipped'],
            $result['types_created'],
        ));
        $this->line('Site stock quantities were not changed.');

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
        $codes = [];

        foreach ($products as $index => $product) {
            $validator = Validator::make($product, [
                'product_code' => ['required', 'string', 'max:100', 'regex:/^[A-Z0-9\-]+$/'],
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
            $code = strtoupper($row['product_code']);

            if (isset($codes[$code])) {
                throw new RuntimeException("Manifest contains duplicate product code: {$code}");
            }

            $codes[$code] = true;
            $row['product_code'] = $code;
            $row['product_type']['code'] = strtoupper($row['product_type']['code']);
            $validated[] = $row;
        }

        return $validated;
    }

    private function preview(array $products): array
    {
        $manifestCodes = collect($products)->pluck('product_code');
        $existingCodes = Product::withTrashed()
            ->whereIn('product_code', $manifestCodes)
            ->pluck('product_code')
            ->map(fn (string $code): string => strtoupper($code))
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
                ->reject(fn (array $product): bool => $existingCodes->has($product['product_code']))
                ->count(),
            'skip_count' => collect($products)
                ->filter(fn (array $product): bool => $existingCodes->has($product['product_code']))
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
            $existingProduct = Product::withTrashed()
                ->where('product_code', $row['product_code'])
                ->first();

            if ($existingProduct) {
                if ($existingProduct->trashed()) {
                    throw new RuntimeException(
                        "Product code {$row['product_code']} belongs to a deleted product."
                    );
                }

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
                'product_code' => $row['product_code'],
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
}
