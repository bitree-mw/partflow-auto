<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductReference;
use App\Models\ProductType;
use App\Models\SiteStock;
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
            'schema_version' => 1,
            'import_key' => 'january-2026-workbook-products',
            'products' => [[
                'product_code' => 'HL-JAN26-12345678',
                'product_name' => 'Head Lamp Test Vehicle',
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
        $this->assertDatabaseCount('product_references', 1);
        $this->assertDatabaseCount('site_stocks', 0);

        $product = Product::query()->firstOrFail();
        $this->assertSame('HL-JAN26-12345678', $product->product_code);
        $this->assertSame('350000.00', $product->default_selling_price);
        $this->assertSame('Head Lamp', ProductType::query()->firstOrFail()->name);
        $this->assertSame(
            'TEST-HEAD-LAMP',
            ProductReference::query()->firstOrFail()->reference_value
        );
        $this->assertFalse(SiteStock::query()->exists());
    }
}
