<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\User;
use App\Models\UserSiteAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueMultiSiteStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_site_filter_keeps_both_pills_for_shared_products_and_limits_rows_to_the_selected_site(): void
    {
        $role = Role::query()->create([
            'name' => 'Catalogue Administrator',
            'permissions' => ['*'],
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $store = Site::query()->create([
            'name' => 'Limbe Store',
            'code' => 'LMBST',
            'type' => 'shop',
            'is_active' => true,
        ]);
        $warehouse = Site::query()->create([
            'name' => 'Limbe Warehouse',
            'code' => 'LMBWH',
            'type' => 'warehouse',
            'is_active' => true,
        ]);
        $productType = ProductType::query()->create([
            'name' => 'Multi-site Part',
            'code' => 'MSP',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'product_code' => 'MSP-001',
            'product_name' => 'Multi-site Catalogue Part',
            'product_type_id' => $productType->id,
            'default_selling_price' => 10000,
            'default_low_stock_level' => 0,
            'is_active' => true,
        ]);

        SiteStock::query()->create([
            'site_id' => $store->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 5,
            'reserved_quantity' => 0,
            'low_stock_level' => 0,
        ]);
        SiteStock::query()->create([
            'site_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 12,
            'reserved_quantity' => 0,
            'low_stock_level' => 0,
        ]);
        $warehouseOnly = Product::query()->create([
            'product_code' => 'MSP-002',
            'product_name' => 'Warehouse Only Part',
            'product_type_id' => $productType->id,
            'default_selling_price' => 10000,
            'default_low_stock_level' => 0,
            'is_active' => true,
        ]);
        SiteStock::query()->create([
            'site_id' => $warehouse->id,
            'product_id' => $warehouseOnly->id,
            'quantity_on_hand' => 7,
            'reserved_quantity' => 0,
            'low_stock_level' => 0,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['pos_site_id' => $store->id])
            ->get(route('web.catalog.products.index'));

        $response
            ->assertOk()
            ->assertSee('Multi-site Catalogue Part')
            ->assertSee('<span>Limbe Store</span>', false)
            ->assertSee('<span>Limbe Warehouse</span>', false)
            ->assertSee('<strong>5</strong>', false)
            ->assertSee('<strong>12</strong>', false);

        $storeResponse = $this->actingAs($user)
            ->withSession(['pos_site_id' => $warehouse->id])
            ->get(route('web.catalog.products.index', ['site_id' => $store->id]));

        $storeResponse
            ->assertOk()
            ->assertSee('Multi-site Catalogue Part')
            ->assertDontSee('Warehouse Only Part')
            ->assertSee('<span>Limbe Store</span>', false)
            ->assertSee('<strong>5</strong>', false)
            ->assertSee('<span>Limbe Warehouse</span>', false)
            ->assertSee('<strong>12</strong>', false)
            ->assertViewHas('products', fn ($products): bool => $products->getCollection()->firstWhere('code', 'MSP-001')['stock'] === 5);

        $warehouseResponse = $this->actingAs($user)
            ->withSession(['pos_site_id' => $store->id])
            ->get(route('web.catalog.products.index', ['site_id' => $warehouse->id]));

        $warehouseResponse
            ->assertOk()
            ->assertSee('Multi-site Catalogue Part')
            ->assertSee('<span>Limbe Warehouse</span>', false)
            ->assertSee('<strong>12</strong>', false)
            ->assertSee('<span>Limbe Store</span>', false)
            ->assertSee('<strong>5</strong>', false)
            ->assertViewHas('products', fn ($products): bool => $products->getCollection()->firstWhere('code', 'MSP-001')['stock'] === 12);
    }

    public function test_catalogue_stock_pills_do_not_reveal_sites_the_user_cannot_access(): void
    {
        $role = Role::query()->create([
            'name' => 'Store Catalogue Manager',
            'permissions' => ['catalogue.view', 'catalogue.manage'],
            'is_active' => true,
        ]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $store = Site::query()->create(['name' => 'Limbe Store', 'code' => 'LMBST', 'type' => 'shop', 'is_active' => true]);
        $warehouse = Site::query()->create(['name' => 'Limbe Warehouse', 'code' => 'LMBWH', 'type' => 'warehouse', 'is_active' => true]);
        UserSiteAccess::query()->create(['user_id' => $user->id, 'site_id' => $store->id, 'is_active' => true]);
        $productType = ProductType::query()->create(['name' => 'Shared Part', 'code' => 'SHP', 'is_active' => true]);
        $product = Product::query()->create([
            'product_code' => 'SHP-001',
            'product_name' => 'Shared Catalogue Part',
            'product_type_id' => $productType->id,
            'default_selling_price' => 10000,
            'is_active' => true,
        ]);
        SiteStock::query()->create(['site_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 0]);
        SiteStock::query()->create(['site_id' => $warehouse->id, 'product_id' => $product->id, 'quantity_on_hand' => 12]);

        $this->actingAs($user)
            ->get(route('web.catalog.products.index'))
            ->assertOk()
            ->assertSee('<span>Limbe Store</span>', false)
            ->assertDontSee('<span>Limbe Warehouse</span>', false)
            ->assertDontSee('Deactivate product', false)
            ->assertViewHas('products', fn ($products): bool => $products->getCollection()->firstWhere('code', 'SHP-001')['stock'] === 0);

        $this->actingAs($user)
            ->get(route('web.catalog.products.index', ['stock_status' => 'out']))
            ->assertOk()
            ->assertSee('Shared Catalogue Part')
            ->assertDontSee('<span>Limbe Warehouse</span>', false);

        $this->actingAs($user)
            ->get(route('web.catalog.products.index', ['site_id' => $warehouse->id]))
            ->assertForbidden();
    }
}
