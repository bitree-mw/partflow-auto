<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductReference;
use App\Models\ProductType;
use App\Models\Site;
use App\Models\SiteStock;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ImportWorkbookProductsCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manifestPath = storage_path('framework/testing/workbook-product-import.json');
        $directory = dirname($this->manifestPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($this->manifestPath, json_encode([
            'schema_version' => 2,
            'import_key' => 'january-2026-workbook-products',
            'products' => [[
                'source_key' => 'JAN26-12345678',
                'product_name' => 'Head Lamp',
                'product_type' => [
                    'name' => 'Head Lamp',
                    'code' => 'HL',
                    'description' => 'Imported head lamp.',
                ],
                'default_selling_price' => 350000,
                'default_low_stock_level' => 0,
                'unit_name' => 'piece',
                'pack_size' => 1,
                'is_active' => true,
                'description' => 'Imported test product.',
                'references' => [[
                    'reference_type' => 'supplier_code',
                    'reference_value' => 'TEST-HEAD-LAMP',
                    'is_primary' => true,
                    'notes' => 'Test reference.',
                ], [
                    'reference_type' => 'other',
                    'reference_value' => 'JAN26-12345678',
                    'is_primary' => false,
                    'notes' => 'Test provenance reference.',
                ]],
            ]],
        ], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        if (isset($this->manifestPath) && is_file($this->manifestPath)) {
            unlink($this->manifestPath);
        }

        parent::tearDown();
    }

    public function test_dry_run_does_not_write_to_the_database(): void
    {
        $exitCode = Artisan::call('products:import-workbook-manifest', [
            'manifest' => $this->manifestPath,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_types', 0);
    }

    public function test_import_is_idempotent_and_does_not_create_site_stock(): void
    {
        $firstExitCode = Artisan::call('products:import-workbook-manifest', [
            'manifest' => $this->manifestPath,
        ]);
        $secondExitCode = Artisan::call('products:import-workbook-manifest', [
            'manifest' => $this->manifestPath,
        ]);

        $this->assertSame(0, $firstExitCode);
        $this->assertSame(0, $secondExitCode);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_types', 1);
        $this->assertDatabaseCount('product_references', 2);
        $this->assertDatabaseCount('site_stocks', 0);

        $product = Product::query()->firstOrFail();
        $this->assertSame('HL-UNKN-UNK-001', $product->product_code);
        $this->assertSame('Head Lamp', $product->product_name);
        $this->assertNull($product->car_model_id);
        $this->assertNull($product->part_country_of_origin);
        $this->assertSame('350000.00', $product->default_selling_price);
        $this->assertSame('Head Lamp', ProductType::query()->firstOrFail()->name);
        $this->assertSame(
            'TEST-HEAD-LAMP',
            ProductReference::query()
                ->where('reference_type', 'supplier_code')
                ->firstOrFail()
                ->reference_value
        );
        $this->assertFalse(SiteStock::query()->exists());
    }

    public function test_replace_option_removes_only_the_legacy_import(): void
    {
        $productType = ProductType::query()->create([
            'name' => 'Head Lamp',
            'code' => 'HL',
            'description' => null,
            'is_active' => true,
        ]);
        $productService = app(ProductService::class);
        $historyProduct = $productService->create([
            'product_code' => 'HL-JAN26-12345678',
            'product_name' => 'Wrong Vehicle Head Lamp',
            'product_type_id' => $productType->id,
            'default_selling_price' => 1,
        ]);
        $productService->create([
            'product_code' => 'HL-JAN26-DEADBEEF',
            'product_name' => 'Unblocked Legacy Product',
            'product_type_id' => $productType->id,
            'default_selling_price' => 1,
        ]);
        $unrelated = $productService->create([
            'product_code' => 'KEEP-ME-001',
            'product_name' => 'Keep Me',
            'product_type_id' => $productType->id,
            'default_selling_price' => 2,
        ]);
        $site = Site::query()->create([
            'name' => 'Test Site',
            'code' => 'TEST',
            'type' => 'shop',
            'is_active' => true,
        ]);
        SiteStock::query()->create([
            'product_id' => $historyProduct->id,
            'site_id' => $site->id,
            'quantity_on_hand' => 3,
            'reserved_quantity' => 0,
        ]);

        $exitCode = Artisan::call('products:import-workbook-manifest', [
            'manifest' => $this->manifestPath,
            '--replace-existing-import' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseMissing('products', ['product_code' => 'HL-JAN26-DEADBEEF']);
        $this->assertDatabaseHas('products', ['id' => $unrelated->id, 'product_code' => 'KEEP-ME-001']);
        $this->assertDatabaseHas('products', [
            'id' => $historyProduct->id,
            'product_code' => 'HL-UNKN-UNK-001',
            'product_name' => 'Head Lamp',
            'car_model_id' => null,
            'default_selling_price' => 1,
        ]);
        $this->assertDatabaseHas('site_stocks', [
            'product_id' => $historyProduct->id,
            'site_id' => $site->id,
            'quantity_on_hand' => 3,
        ]);
    }
}
