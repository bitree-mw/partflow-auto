<?php

namespace App\Console\Commands;

use App\Services\ClientCatalogSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use JsonException;
use RuntimeException;
use Throwable;

class SyncClientCatalog extends Command
{
    protected $signature = 'catalog:sync-client-manifest
        {manifest=database/data/september_2026_client_catalog.json : Absolute path or project-relative manifest path}
        {--dry-run : Validate and preview changes without writing to the database}
        {--force : Confirm deletion of operational history and replacement of the product catalogue}';

    protected $description = 'Reset operational data and load the September 2026 warehouse catalogue and opening stock';

    public function __construct(private readonly ClientCatalogSyncService $syncService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $manifest = $this->validatedManifest($this->readManifest((string) $this->argument('manifest')));
            $preview = $this->syncService->preview($manifest);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            [
                'Manifest products',
                'Products deleted',
                'Documents deleted',
                'Contacts deleted',
                'Expenses deleted',
                'Warehouse qty',
                'Priced',
                'Zero price',
            ],
            [[
                $preview['manifest_products'],
                $preview['products_to_delete'],
                $preview['documents_to_delete'],
                $preview['contacts_to_delete'],
                $preview['expenses_to_delete'],
                $preview['warehouse_quantity'],
                $preview['priced_products'],
                $preview['zero_price_products'],
            ]]
        );

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. No database changes were made.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->error('Re-run with --force to delete operational history and replace the product catalogue.');

            return self::FAILURE;
        }

        try {
            $result = $this->syncService->sync($manifest);
        } catch (Throwable $exception) {
            $this->error('Reconciliation rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Warehouse reset complete: %d products created after deleting %d existing products.',
            $result['products_created'],
            $result['products_deleted'],
        ));
        $this->line(sprintf(
            'Deleted %d old inventory documents, %d stock movements, %d contacts, and %d expenses.',
            $result['documents_deleted'],
            $result['stock_movements_deleted'],
            $result['contacts_deleted'],
            $result['expenses_deleted'],
        ));
        $this->line(sprintf(
            'Opening warehouse stock applied: %d units at Limbe Warehouse in adjustment #%d.',
            $result['warehouse_quantity'],
            $result['opening_adjustment_id'],
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

        if (($manifest['import_key'] ?? null) !== 'september-2026-client-catalog') {
            throw new RuntimeException('Manifest import_key is not recognized.');
        }

        return $manifest;
    }

    private function validatedManifest(array $manifest): array
    {
        $topLevel = Validator::make($manifest, [
            'brands' => ['required', 'array', 'min:1'],
            'retired_product_types' => ['present', 'array'],
            'retired_product_types.*' => ['required', 'string', 'distinct'],
            'sites' => ['required', 'array', 'min:1'],
            'sites.*' => ['required', 'string', 'distinct'],
            'products' => ['required', 'array', 'min:1'],
        ]);

        if ($topLevel->fails()) {
            throw new RuntimeException('Manifest is invalid: '.$topLevel->errors()->first());
        }

        $brands = [];
        foreach ($manifest['brands'] as $index => $brand) {
            $validator = Validator::make($brand, [
                'name' => ['required', 'string', 'max:255'],
                'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9]+$/'],
                'country' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
            ]);
            if ($validator->fails()) {
                throw new RuntimeException("Manifest brand at index {$index} is invalid: ".$validator->errors()->first());
            }
            $row = $validator->validated();
            if (isset($brands[$row['code']])) {
                throw new RuntimeException("Manifest contains duplicate brand code: {$row['code']}.");
            }
            $brands[$row['code']] = $row;
        }

        $products = [];
        $sourceKeys = [];
        $productNames = [];
        $typeCodes = [];
        foreach ($manifest['products'] as $index => $product) {
            $validator = Validator::make($product, [
                'source_key' => ['required', 'string', 'regex:/^SEP26-[A-F0-9]{12}$/'],
                'product_name' => ['required', 'string', 'max:255'],
                'product_type' => ['required', 'array'],
                'product_type.name' => ['required', 'string', 'max:255'],
                'product_type.code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/'],
                'product_type.description' => ['nullable', 'string'],
                'brand_code' => ['required', Rule::in(array_keys($brands))],
                'part_country_of_origin' => ['nullable', 'string', 'max:255'],
                'default_selling_price' => ['required', 'numeric', 'min:0'],
                'default_low_stock_level' => ['required', 'integer', 'min:0'],
                'unit_name' => ['required', 'string', 'max:255'],
                'pack_size' => ['required', 'numeric', 'min:0.01'],
                'is_active' => ['required', 'boolean'],
                'description' => ['required', 'string'],
                'source_rows' => ['required', 'array', 'min:1'],
                'source_rows.*' => ['required', 'string', 'regex:/^Warehouse_September_2026\.xlsx\/.+\/row [0-9]+$/'],
                'match_reference_values' => ['present', 'array'],
                'match_reference_values.*' => ['required', 'string', 'distinct'],
                'stocks' => ['required', 'array'],
                'references' => ['required', 'array', 'min:1'],
                'references.*.reference_type' => ['required', Rule::in(['barcode', 'oem_number', 'supplier_code', 'aftermarket_code', 'other'])],
                'references.*.reference_value' => ['required', 'string', 'max:255'],
                'references.*.is_primary' => ['required', 'boolean'],
                'references.*.notes' => ['nullable', 'string'],
            ]);

            foreach ($manifest['sites'] as $siteCode) {
                $validator->addRules(["stocks.{$siteCode}" => ['required', 'integer', 'min:0']]);
            }

            if ($validator->fails()) {
                throw new RuntimeException("Manifest product at index {$index} is invalid: ".$validator->errors()->first());
            }

            $row = $validator->validated();
            $nonWarehouseQuantity = collect($row['stocks'])
                ->except('LMBWH')
                ->sum();

            if ($nonWarehouseQuantity !== 0) {
                throw new RuntimeException("Manifest product {$row['source_key']} contains stock outside Limbe Warehouse.");
            }

            if (isset($sourceKeys[$row['source_key']])) {
                throw new RuntimeException("Manifest contains duplicate source key: {$row['source_key']}.");
            }
            if (isset($productNames[mb_strtolower($row['product_name'])])) {
                throw new RuntimeException("Manifest contains duplicate product name: {$row['product_name']}.");
            }
            $typeCode = $row['product_type']['code'];
            if (isset($typeCodes[$typeCode]) && $typeCodes[$typeCode] !== $row['product_type']['name']) {
                throw new RuntimeException("Manifest product type code {$typeCode} has conflicting names.");
            }
            $hasSourceReference = collect($row['references'])->contains(fn (array $reference): bool => $reference['reference_type'] === 'other' && $reference['reference_value'] === $row['source_key']
            );
            if (! $hasSourceReference) {
                throw new RuntimeException("Manifest product {$row['source_key']} is missing its provenance reference.");
            }

            $sourceKeys[$row['source_key']] = true;
            $productNames[mb_strtolower($row['product_name'])] = true;
            $typeCodes[$typeCode] = $row['product_type']['name'];
            $products[] = $row;
        }

        $manifest['brands'] = array_values($brands);
        $manifest['products'] = $products;

        return $manifest;
    }
}
