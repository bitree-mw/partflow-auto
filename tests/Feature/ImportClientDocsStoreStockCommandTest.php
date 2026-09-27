<?php

namespace Tests\Feature;

use App\Models\InventoryDocument;
use App\Models\Product;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ImportClientDocsStoreStockCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_change_the_database(): void
    {
        User::factory()->create(['is_active' => true]);
        Site::query()->create(['name' => 'Limbe Store', 'code' => 'LMBST', 'type' => 'shop', 'is_active' => true]);

        $exitCode = Artisan::call('catalog:import-client-docs-store-stock', [
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('site_stocks', 0);
        $this->assertDatabaseCount('inventory_documents', 0);
    }

    public function test_it_reuses_reviewed_matches_and_imports_verified_store_quantities_idempotently(): void
    {
        User::factory()->create(['is_active' => true]);
        $store = Site::query()->create(['name' => 'Limbe Store', 'code' => 'LMBST', 'type' => 'shop', 'is_active' => true]);
        $warehouse = Site::query()->create(['name' => 'Limbe Warehouse', 'code' => 'LMBWH', 'type' => 'warehouse', 'is_active' => true]);

        $this->artisan('catalog:sync-client-manifest', [
            'manifest' => base_path('database/data/september_2026_client_catalog.json'),
            '--force' => true,
        ])->assertSuccessful();

        $matchedProduct = Product::query()
            ->where('product_name', 'Radiator - Mazda Demio small tank')
            ->firstOrFail();

        $firstExitCode = Artisan::call('catalog:import-client-docs-store-stock');
        $firstOutput = Artisan::output();
        $secondExitCode = Artisan::call('catalog:import-client-docs-store-stock');

        $this->assertSame(0, $firstExitCode, $firstOutput);
        $this->assertSame(0, $secondExitCode, Artisan::output());
        $this->assertDatabaseCount('products', 644);
        $this->assertDatabaseCount('inventory_documents', 2);
        $this->assertDatabaseCount('inventory_document_items', 645);
        $this->assertDatabaseCount('stock_movements', 645);
        $this->assertSame(1356, SiteStock::query()->where('site_id', $warehouse->id)->sum('quantity_on_hand'));
        $this->assertSame(1009, SiteStock::query()->where('site_id', $store->id)->sum('quantity_on_hand'));
        $this->assertSame(200, SiteStock::query()->where('site_id', $store->id)->count());
        $this->assertSame(19, SiteStock::query()
            ->where('site_id', $store->id)
            ->where('quantity_on_hand', 0)
            ->count());
        $this->assertDatabaseHas('site_stocks', [
            'product_id' => $matchedProduct->id,
            'site_id' => $store->id,
            'quantity_on_hand' => 1,
        ]);
        $this->assertDatabaseHas('product_references', [
            'product_id' => $matchedProduct->id,
            'reference_type' => 'other',
            'reference_value' => 'JAN26-F2F20175',
        ]);
        $this->assertDatabaseHas('products', [
            'product_name' => 'CV Joint - Toyota Noah / Voxy',
        ]);
        $this->assertDatabaseHas('site_stocks', [
            'site_id' => $store->id,
            'quantity_on_hand' => 4,
            'product_id' => Product::query()
                ->where('product_name', 'Fuel Pump - SPAREX SIPE03 Toyota Quantum')
                ->value('id'),
        ]);
        $this->assertSame(1, InventoryDocument::query()
            ->where('document_type', 'stock_take')
            ->where('source_site_id', $store->id)
            ->count());
        $this->assertSame(181, StockMovement::query()->where('site_id', $store->id)->count());
    }
}
