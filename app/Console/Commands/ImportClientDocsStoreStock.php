<?php

namespace App\Console\Commands;

use App\Services\ClientDocsInventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use JsonException;
use RuntimeException;
use Throwable;

class ImportClientDocsStoreStock extends Command
{
    private const INCLUDED_SOURCES = [
        'Head Lamp.xlsx',
        'NEW HOPE.xlsx',
        'Radiator.xlsx',
        'SHOCK STEEERING AND AXEL.xlsx',
        'Tail Lamp.xlsx',
        'USED.xlsx',
        'CPT.xlsx',
    ];

    protected $signature = 'catalog:import-client-docs-store-stock
        {source-manifest=database/data/source_catalog_products.json : Source product and quantity manifest}
        {warehouse-manifest=database/data/september_2026_client_catalog.json : Warehouse manifest containing reviewed matches}
        {--dry-run : Validate and preview changes without writing to the database}';

    protected $description = 'Import the verified client workbook and CPT products into Limbe Store stock';

    public function __construct(private readonly ClientDocsInventoryService $inventoryService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $sourceManifest = $this->readManifest((string) $this->argument('source-manifest'));
            $warehouseManifest = $this->readManifest((string) $this->argument('warehouse-manifest'));
            $products = $this->validatedProducts($sourceManifest);
            $matches = $this->validatedMatches($warehouseManifest, $products);
            $preview = $this->inventoryService->preview($products, $matches);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Source products', 'Store quantity', 'Reviewed matches', 'Already imported', 'Will create', 'Zero quantity'],
            [[
                $preview['source_products'],
                $preview['source_quantity'],
                $preview['warehouse_matches'],
                $preview['already_imported'],
                $preview['new_products'],
                $preview['zero_quantity_products'],
            ]]
        );

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. No database changes were made.');

            return self::SUCCESS;
        }

        try {
            $result = $this->inventoryService->apply($products, $matches);
        } catch (Throwable $exception) {
            $this->error('Store inventory import rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Store inventory import complete: %d products created, %d products reused, %d store products, %d units.',
            $result['products_created'],
            $result['products_reused'],
            $result['store_products'],
            $result['store_quantity'],
        ));
        $this->line(sprintf(
            '%d provenance references added; %d stock rows changed%s.',
            $result['references_added'],
            $result['stock_rows_changed'],
            $result['stock_take_id'] ? " in stock take #{$result['stock_take_id']}" : '',
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
            $manifest = json_decode(file_get_contents($path) ?: '', true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Manifest JSON is invalid: '.$exception->getMessage(), 0, $exception);
        }

        if (! is_array($manifest) || ($manifest['schema_version'] ?? null) !== 1) {
            throw new RuntimeException('Manifest schema_version must be 1.');
        }

        return $manifest;
    }

    private function validatedProducts(array $manifest): array
    {
        if (($manifest['import_key'] ?? null) !== 'full-source-catalog-replacement') {
            throw new RuntimeException('Source manifest import_key is not recognized.');
        }

        $rows = $manifest['products'] ?? null;

        if (! is_array($rows) || $rows === []) {
            throw new RuntimeException('Source manifest products must be a non-empty array.');
        }

        $products = [];
        $sourceKeys = [];
        $allowedReferenceTypes = ['barcode', 'oem_number', 'supplier_code', 'aftermarket_code', 'other'];

        foreach ($rows as $index => $row) {
            $sourceFile = explode('/', (string) ($row['source_rows'] ?? ''))[0];

            if (! in_array($sourceFile, self::INCLUDED_SOURCES, true)) {
                continue;
            }

            $validator = Validator::make($row, [
                'source_key' => ['required', 'string', 'regex:/^(?:JAN26|CPT)-[A-F0-9]{8}$/'],
                'product_name' => ['required', 'string', 'max:255'],
                'product_type.name' => ['required', 'string', 'max:255'],
                'product_type.code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/'],
                'product_type.description' => ['nullable', 'string'],
                'source_rows' => ['required', 'string'],
                'quantity_on_hand' => ['required', 'integer', 'min:0'],
                'default_selling_price' => ['required', 'numeric', 'min:0'],
                'default_low_stock_level' => ['required', 'integer', 'min:0'],
                'unit_name' => ['required', 'string', 'max:255'],
                'pack_size' => ['required', 'numeric', 'min:0.01'],
                'is_active' => ['required', 'boolean'],
                'description' => ['required', 'string'],
                'references' => ['required', 'array', 'min:1'],
                'references.*.reference_type' => ['required', Rule::in($allowedReferenceTypes)],
                'references.*.reference_value' => ['required', 'string', 'max:255'],
                'references.*.is_primary' => ['required', 'boolean'],
                'references.*.notes' => ['nullable', 'string'],
            ]);

            if ($validator->fails()) {
                throw new RuntimeException("Source product at index {$index} is invalid: ".$validator->errors()->first());
            }

            $product = $validator->validated();
            $sourceKey = strtoupper($product['source_key']);

            if (isset($sourceKeys[$sourceKey])) {
                throw new RuntimeException("Source manifest contains duplicate source key: {$sourceKey}.");
            }

            $sourcePaths = explode('; ', $product['source_rows']);
            foreach ($sourcePaths as $sourcePath) {
                if (! str_starts_with($sourcePath, $sourceFile.'/')) {
                    throw new RuntimeException("Source product {$sourceKey} mixes workbook sources.");
                }
            }

            $hasSourceReference = collect($product['references'])->contains(
                fn (array $reference): bool => $reference['reference_type'] === 'other' &&
                    strtoupper($reference['reference_value']) === $sourceKey
            );

            if (! $hasSourceReference) {
                throw new RuntimeException("Source product {$sourceKey} is missing its provenance reference.");
            }

            $product['source_key'] = $sourceKey;
            $product['product_type']['code'] = strtoupper($product['product_type']['code']);
            $sourceKeys[$sourceKey] = true;
            $products[] = $product;
        }

        if (count($products) !== 200) {
            throw new RuntimeException(sprintf(
                'Expected 200 products from the six client workbooks and CPT source, found %d.',
                count($products),
            ));
        }

        return $products;
    }

    private function validatedMatches(array $manifest, array $products): array
    {
        if (($manifest['import_key'] ?? null) !== 'september-2026-client-catalog') {
            throw new RuntimeException('Warehouse manifest import_key is not recognized.');
        }

        $sourceKeys = collect($products)->pluck('source_key')->flip();
        $matches = [];

        foreach ($manifest['products'] ?? [] as $index => $product) {
            $validator = Validator::make($product, [
                'source_key' => ['required', 'string', 'regex:/^SEP26-[A-F0-9]{12}$/'],
                'match_reference_values' => ['present', 'array'],
                'match_reference_values.*' => ['required', 'string'],
            ]);

            if ($validator->fails()) {
                throw new RuntimeException("Warehouse product at index {$index} is invalid: ".$validator->errors()->first());
            }

            $row = $validator->validated();
            foreach ($row['match_reference_values'] as $sourceKey) {
                $sourceKey = strtoupper($sourceKey);

                if (! $sourceKeys->has($sourceKey)) {
                    continue;
                }

                if (isset($matches[$sourceKey])) {
                    throw new RuntimeException("Warehouse manifest contains duplicate reviewed match: {$sourceKey}.");
                }

                $matches[$sourceKey] = strtoupper($row['source_key']);
            }
        }

        return $matches;
    }
}
