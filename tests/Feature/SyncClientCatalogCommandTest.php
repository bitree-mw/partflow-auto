<?php

namespace Tests\Feature;

use App\Models\CarMake;
use App\Models\Contact;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryDocument;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\VehicleModel;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncClientCatalogCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_clears_operational_data_and_rebuilds_warehouse_stock(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = Site::query()->create(['name' => 'Limbe Store', 'code' => 'LMBST', 'type' => 'shop', 'is_active' => true]);
        $warehouse = Site::query()->create(['name' => 'Limbe Warehouse', 'code' => 'LMBWH', 'type' => 'warehouse', 'is_active' => true]);
        $oldType = ProductType::query()->create(['name' => 'Old Type', 'code' => 'OLD', 'is_active' => true]);
        $make = CarMake::query()->create(['name' => 'Toyota', 'code' => 'TOY', 'is_active' => true]);
        $model = VehicleModel::query()->create(['car_make_id' => $make->id, 'name' => 'Vitz', 'code' => 'VITZ', 'is_active' => true]);
        $oldProduct = app(ProductService::class)->create([
            'product_name' => 'Old Product',
            'product_type_id' => $oldType->id,
        ]);
        SiteStock::query()->create([
            'product_id' => $oldProduct->id,
            'site_id' => $store->id,
            'quantity_on_hand' => 9,
            'reserved_quantity' => 0,
        ]);

        foreach (['sale', 'purchase', 'stock_take', 'transfer'] as $type) {
            InventoryDocument::query()->create([
                'document_number' => strtoupper($type).'-OLD',
                'document_type' => $type,
                'source_site_id' => $store->id,
                'destination_site_id' => $warehouse->id,
                'document_date' => now(),
                'status' => 'completed',
                'created_by' => $user->id,
            ]);
        }

        Contact::query()->create([
            'contact_type' => 'customer',
            'code' => 'OLD-CUSTOMER',
            'name' => 'Old Customer',
            'is_active' => true,
        ]);
        $expenseCategory = ExpenseCategory::query()->create(['name' => 'Old Expense', 'is_active' => true]);
        Expense::query()->create([
            'expense_category_id' => $expenseCategory->id,
            'site_id' => $store->id,
            'expense_date' => now(),
            'amount' => 100,
            'description' => 'Old expense',
            'created_by' => $user->id,
        ]);

        $manifestPath = $this->writeManifest([
            $this->productRow('SEP26-AAAAAAAAAAAA', 'Radiator - Nissan Note', 'Radiator', 'RAD', 4, 380000),
            $this->productRow('SEP26-BBBBBBBBBBBB', 'Fuel Tank Sender Unit - Toyota Vitz', 'Fuel Tank Sender Unit', 'FTSU', 0, 90000),
        ]);

        try {
            $exitCode = Artisan::call('catalog:sync-client-manifest', [
                'manifest' => $manifestPath,
                '--force' => true,
            ]);

            $this->assertSame(0, $exitCode, Artisan::output());
        } finally {
            @unlink($manifestPath);
        }

        $this->assertDatabaseMissing('products', ['id' => $oldProduct->id]);
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('inventory_documents', 1);
        $this->assertDatabaseHas('inventory_documents', ['document_type' => 'adjustment', 'status' => 'approved']);
        $this->assertDatabaseMissing('inventory_documents', ['document_type' => 'stock_take']);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('site_stocks', 2);
        $this->assertDatabaseHas('product_types', ['id' => $oldType->id, 'name' => 'Old Type']);
        $this->assertDatabaseHas('car_makes', ['id' => $make->id, 'name' => 'Toyota']);
        $this->assertDatabaseHas('vehicle_models', ['id' => $model->id, 'name' => 'Vitz']);

        $radiator = Product::query()->where('product_name', 'Radiator - Nissan Note')->firstOrFail();
        $zeroStockProduct = Product::query()->where('product_name', 'Fuel Tank Sender Unit - Toyota Vitz')->firstOrFail();

        $this->assertSame(4, $this->stock($radiator, $warehouse));
        $this->assertSame(0, $this->stock($zeroStockProduct, $warehouse));
        $this->assertSame(0, SiteStock::query()->where('site_id', $store->id)->count());
    }

    public function test_dry_run_does_not_change_database(): void
    {
        User::factory()->create(['is_active' => true]);
        Site::query()->create(['name' => 'Limbe Warehouse', 'code' => 'LMBWH', 'type' => 'warehouse', 'is_active' => true]);
        $manifestPath = $this->writeManifest([
            $this->productRow('SEP26-DDDDDDDDDDDD', 'Head Lamp Left - Toyota Vitz', 'Head Lamp Left', 'HLL', 1, 300000),
        ]);

        try {
            $exitCode = Artisan::call('catalog:sync-client-manifest', [
                'manifest' => $manifestPath,
                '--dry-run' => true,
            ]);
            $output = Artisan::output();

            $this->assertSame(0, $exitCode, $output);
            $this->assertStringContainsString('Dry run complete', $output);
        } finally {
            @unlink($manifestPath);
        }

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('inventory_documents', 0);
    }

    public function test_complete_warehouse_manifest_rebuilds_with_expected_totals(): void
    {
        User::factory()->create(['is_active' => true]);
        $store = Site::query()->create(['name' => 'Limbe Store', 'code' => 'LMBST', 'type' => 'shop', 'is_active' => true]);
        $warehouse = Site::query()->create(['name' => 'Limbe Warehouse', 'code' => 'LMBWH', 'type' => 'warehouse', 'is_active' => true]);

        $this->artisan('catalog:sync-client-manifest', [
            'manifest' => base_path('database/data/september_2026_client_catalog.json'),
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseCount('products', 466);
        $this->assertDatabaseCount('site_stocks', 466);
        $this->assertDatabaseCount('inventory_documents', 1);
        $this->assertDatabaseCount('inventory_document_items', 464);
        $this->assertDatabaseCount('stock_movements', 464);
        $this->assertDatabaseMissing('inventory_documents', ['document_type' => 'stock_take']);
        $this->assertSame(1356, SiteStock::query()->where('site_id', $warehouse->id)->sum('quantity_on_hand'));
        $this->assertSame(0, SiteStock::query()->where('site_id', $store->id)->count());
        $this->assertSame(2, SiteStock::query()->where('site_id', $warehouse->id)->where('quantity_on_hand', 0)->count());
        $this->assertTrue(Product::query()->pluck('product_code')->every(
            fn (string $code): bool => preg_match('/^[A-Z0-9]+-UNKN-UNK-[0-9]{3}$/', $code) === 1
        ));
    }

    private function stock(Product $product, Site $site): int
    {
        return (int) SiteStock::query()
            ->where('product_id', $product->id)
            ->where('site_id', $site->id)
            ->value('quantity_on_hand');
    }

    private function writeManifest(array $products): string
    {
        $path = tempnam(sys_get_temp_dir(), 'partflow-client-catalog-');
        file_put_contents($path, json_encode([
            'schema_version' => 1,
            'import_key' => 'september-2026-client-catalog',
            'brands' => [[
                'name' => 'Unknown',
                'code' => 'UNKN',
                'country' => null,
                'description' => 'Fallback brand for unconfirmed products.',
            ]],
            'retired_product_types' => [],
            'sites' => ['LMBST', 'LMBWH'],
            'products' => $products,
        ], JSON_THROW_ON_ERROR));

        return $path;
    }

    private function productRow(
        string $sourceKey,
        string $name,
        string $type,
        string $typeCode,
        int $warehouseQuantity,
        int $price,
    ): array {
        return [
            'source_key' => $sourceKey,
            'product_name' => $name,
            'product_type' => [
                'name' => $type,
                'code' => $typeCode,
                'description' => "{$type} test type.",
            ],
            'brand_code' => 'UNKN',
            'part_country_of_origin' => null,
            'default_selling_price' => $price,
            'default_low_stock_level' => 0,
            'unit_name' => 'piece',
            'pack_size' => 1,
            'is_active' => true,
            'source_rows' => ['Warehouse_September_2026.xlsx/Sheet1/row 2'],
            'description' => 'Imported from Warehouse_September_2026.xlsx.',
            'match_reference_values' => [],
            'stocks' => ['LMBST' => 0, 'LMBWH' => $warehouseQuantity],
            'references' => [[
                'reference_type' => 'other',
                'reference_value' => $sourceKey,
                'is_primary' => true,
                'notes' => 'Warehouse workbook provenance.',
            ]],
        ];
    }
}
