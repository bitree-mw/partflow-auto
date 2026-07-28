<?php

namespace Tests\Feature;

use App\Models\ProductType;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ResetAllStockToZeroCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_change_stock(): void
    {
        [$siteStock] = $this->stockedProduct();

        $exitCode = Artisan::call('stock:reset-all-to-zero', ['--dry-run' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(8, $siteStock->fresh()->quantity_on_hand);
        $this->assertSame(2, $siteStock->fresh()->reserved_quantity);
    }

    public function test_it_resets_stock_and_thresholds_without_changing_selling_prices(): void
    {
        [$siteStock, $product] = $this->stockedProduct();
        $sellingPrice = $product->default_selling_price;

        $exitCode = Artisan::call('stock:reset-all-to-zero', ['--force' => true]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertSame(0, $siteStock->fresh()->quantity_on_hand);
        $this->assertSame(0, $siteStock->fresh()->reserved_quantity);
        $this->assertSame(0, $siteStock->fresh()->low_stock_level);
        $this->assertSame(0, $product->fresh()->default_low_stock_level);
        $this->assertSame($sellingPrice, $product->fresh()->default_selling_price);
        $this->assertDatabaseHas('inventory_documents', [
            'document_type' => 'stock_take',
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'movement_type' => 'stock_take_adjustment',
            'quantity_change' => -8,
            'balance_after' => 0,
        ]);
    }

    private function stockedProduct(): array
    {
        User::factory()->create(['is_active' => true]);
        $site = Site::query()->create([
            'name' => 'Test Site',
            'code' => 'TEST',
            'type' => 'shop',
            'is_active' => true,
        ]);
        $type = ProductType::query()->create([
            'name' => 'Oil Filter',
            'code' => 'OF',
            'is_active' => true,
        ]);
        $product = app(ProductService::class)->create([
            'product_name' => 'Test Oil Filter',
            'product_type_id' => $type->id,
            'default_selling_price' => 25000,
            'default_low_stock_level' => 4,
        ]);
        $siteStock = SiteStock::query()->create([
            'product_id' => $product->id,
            'site_id' => $site->id,
            'quantity_on_hand' => 8,
            'reserved_quantity' => 2,
            'low_stock_level' => 3,
        ]);

        return [$siteStock, $product];
    }
}
