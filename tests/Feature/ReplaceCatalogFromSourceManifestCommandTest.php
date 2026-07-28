<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductType;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\User;
use App\Models\UserSiteAccess;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ReplaceCatalogFromSourceManifestCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manifestPath = storage_path('framework/testing/source-catalog-replacement.json');
        $directory = dirname($this->manifestPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($this->manifestPath, json_encode([
            'schema_version' => 1,
            'import_key' => 'full-source-catalog-replacement',
            'sites' => [[
                'name' => 'Limbe Store',
                'code' => 'LMBST',
                'type' => 'shop',
                'location' => 'Limbe',
            ], [
                'name' => 'Limbe Warehouse',
                'code' => 'LMBWH',
                'type' => 'warehouse',
                'location' => 'Limbe',
            ]],
            'products' => [
                $this->productRow(
                    sourceKey: 'CPT-12345678',
                    name: 'Head Gasket - Mazda Demio',
                    typeName: 'Head Gasket',
                    typeCode: 'HG',
                    siteCode: 'LMBST',
                    vehicleSpecific: true,
                    quantity: 4,
                    price: 100000,
                ),
                $this->productRow(
                    sourceKey: 'CPT-90ABCDEF',
                    name: 'IMAX Engine Oil',
                    typeName: 'Engine Oil',
                    typeCode: 'EOIL',
                    siteCode: 'LMBWH',
                    vehicleSpecific: false,
                    quantity: 2,
                    price: 50000,
                ),
            ],
        ], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        if (isset($this->manifestPath) && is_file($this->manifestPath)) {
            unlink($this->manifestPath);
        }

        parent::tearDown();
    }

    public function test_dry_run_does_not_remove_existing_catalog_records(): void
    {
        $site = Site::query()->create([
            'name' => 'Old Site',
            'code' => 'OLD',
            'type' => 'shop',
            'is_active' => true,
        ]);

        $exitCode = Artisan::call('catalog:replace-from-source-manifest', [
            'manifest' => $this->manifestPath,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('sites', ['id' => $site->id, 'code' => 'OLD']);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_it_replaces_products_sites_stock_and_user_access(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $oldSite = Site::query()->create([
            'name' => 'Old Site',
            'code' => 'OLD',
            'type' => 'shop',
            'is_active' => true,
        ]);
        UserSiteAccess::query()->create([
            'user_id' => $user->id,
            'site_id' => $oldSite->id,
            'access_level' => 'manager',
            'can_view_stock' => true,
            'can_make_sales' => true,
            'can_receive_stock' => true,
            'can_transfer_stock' => true,
            'can_adjust_stock' => true,
            'is_default' => true,
            'is_active' => true,
        ]);
        $oldType = ProductType::query()->create([
            'name' => 'Old Type',
            'code' => 'OLD',
            'is_active' => true,
        ]);
        $oldProduct = app(ProductService::class)->create([
            'product_name' => 'Old Product',
            'product_type_id' => $oldType->id,
        ]);
        SiteStock::query()->create([
            'product_id' => $oldProduct->id,
            'site_id' => $oldSite->id,
            'quantity_on_hand' => 9,
            'reserved_quantity' => 0,
        ]);

        $exitCode = Artisan::call('catalog:replace-from-source-manifest', [
            'manifest' => $this->manifestPath,
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertDatabaseMissing('sites', ['code' => 'OLD']);
        $this->assertDatabaseMissing('products', ['product_name' => 'Old Product']);
        $this->assertSame(
            ['LMBST' => 'Limbe Store', 'LMBWH' => 'Limbe Warehouse'],
            Site::query()->orderBy('code')->pluck('name', 'code')->all()
        );
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseCount('site_stocks', 2);
        $this->assertDatabaseCount('inventory_documents', 2);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseCount('user_site_access', 2);

        $storeProduct = Product::query()
            ->where('product_name', 'Head Gasket - Mazda Demio')
            ->firstOrFail();
        $warehouseProduct = Product::query()
            ->where('product_name', 'IMAX Engine Oil')
            ->firstOrFail();

        $this->assertSame('HG-UNKN-UNK-001', $storeProduct->product_code);
        $this->assertSame('EOIL-UNKN-UNK-001', $warehouseProduct->product_code);
        $this->assertNull($storeProduct->car_model_id);
        $this->assertSame(4, SiteStock::query()
            ->where('product_id', $storeProduct->id)
            ->whereHas('site', fn ($query) => $query->where('code', 'LMBST'))
            ->value('quantity_on_hand'));
        $this->assertSame(2, SiteStock::query()
            ->where('product_id', $warehouseProduct->id)
            ->whereHas('site', fn ($query) => $query->where('code', 'LMBWH'))
            ->value('quantity_on_hand'));
        $this->assertSame(1, UserSiteAccess::query()
            ->where('user_id', $user->id)
            ->where('is_default', true)
            ->whereHas('site', fn ($query) => $query->where('code', 'LMBST'))
            ->count());
    }

    public function test_the_complete_source_manifest_rebuilds_with_expected_totals(): void
    {
        User::factory()->create(['is_active' => true]);

        $exitCode = Artisan::call('catalog:replace-from-source-manifest', [
            'manifest' => base_path('database/data/source_catalog_products.json'),
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertDatabaseCount('sites', 2);
        $this->assertDatabaseCount('products', 308);
        $this->assertDatabaseCount('site_stocks', 308);
        $this->assertDatabaseCount('inventory_documents', 2);
        $this->assertDatabaseCount('inventory_document_items', 289);
        $this->assertDatabaseCount('stock_movements', 289);
        $this->assertSame(1295, SiteStock::query()
            ->whereHas('site', fn ($query) => $query->where('code', 'LMBST'))
            ->sum('quantity_on_hand'));
        $this->assertSame(327, SiteStock::query()
            ->whereHas('site', fn ($query) => $query->where('code', 'LMBWH'))
            ->sum('quantity_on_hand'));
        $this->assertSame(0, Product::query()->whereNotNull('car_model_id')->count());
        $this->assertTrue(Product::query()
            ->pluck('product_code')
            ->every(fn (string $code): bool =>
                preg_match('/^[A-Z0-9]+-UNKN-UNK-[0-9]{3}$/', $code) === 1
            ));
        $this->assertDatabaseHas('products', [
            'product_name' => 'CV Joint - Toyota Noah / Voxy',
            'default_selling_price' => 0,
        ]);
        $this->assertDatabaseHas('product_references', [
            'reference_type' => 'supplier_code',
            'reference_value' => 'TT0.61',
        ]);
    }

    private function productRow(
        string $sourceKey,
        string $name,
        string $typeName,
        string $typeCode,
        string $siteCode,
        bool $vehicleSpecific,
        int $quantity,
        float $price
    ): array {
        return [
            'source_key' => $sourceKey,
            'product_name' => $name,
            'product_type' => [
                'name' => $typeName,
                'code' => $typeCode,
                'description' => 'Imported product type.',
            ],
            'source_part_number' => $sourceKey,
            'source_description' => $name,
            'source_rows' => 'CPT.xlsx/Sheet1/row 2',
            'vehicle_specific' => $vehicleSpecific,
            'stock_site_code' => $siteCode,
            'quantity_on_hand' => $quantity,
            'default_selling_price' => $price,
            'default_low_stock_level' => 0,
            'unit_name' => 'piece',
            'pack_size' => 1,
            'is_active' => true,
            'description' => 'Imported from a source workbook.',
            'references' => [[
                'reference_type' => 'other',
                'reference_value' => $sourceKey,
                'is_primary' => false,
                'notes' => 'Stable source key.',
            ]],
        ];
    }
}
