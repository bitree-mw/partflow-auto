<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosBranchStockSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_uses_the_selected_branch_stock_and_does_not_fall_back_to_another_branch(): void
    {
        $user = $this->adminUser();
        $selectedSite = $this->site('Selected Branch', 'SEL');
        $stockedSite = $this->site('Stocked Branch', 'STK');
        $productType = ProductType::create([
            'name' => 'Branch Sync Parts',
            'code' => 'BSP',
            'is_active' => true,
        ]);
        $product = Product::create([
            'product_code' => 'SYNC-001',
            'product_name' => 'Branch Specific Part',
            'product_type_id' => $productType->id,
            'default_purchase_price' => 1000,
            'default_selling_price' => 1500,
            'is_active' => true,
        ]);

        SiteStock::create([
            'product_id' => $product->id,
            'site_id' => $stockedSite->id,
            'quantity_on_hand' => 7,
            'reserved_quantity' => 0,
            'low_stock_level' => 2,
        ]);

        $this->actingAs($user)
            ->withSession(['pos_site_id' => $selectedSite->id])
            ->getJson(route('web.pos.products'))
            ->assertOk()
            ->assertJsonPath('site_id', $selectedSite->id)
            ->assertJsonCount(0, 'products');

        $this->getJson(route('web.pos.products', ['site_id' => $stockedSite->id]))
            ->assertOk()
            ->assertJsonPath('site_id', $stockedSite->id)
            ->assertJsonPath('site_name', 'Stocked Branch')
            ->assertJsonPath('products.0.product_id', $product->id)
            ->assertJsonPath('products.0.current_branch_stock.available', 7)
            ->assertJsonPath('products.0.current_branch_name', 'Stocked Branch')
            ->assertSessionHas('pos_site_id', $selectedSite->id);

        $this->postJson(route('web.pos.site'), ['site_id' => $stockedSite->id])
            ->assertOk()
            ->assertSessionHas('pos_site_id', $stockedSite->id);

        $this->getJson(route('web.pos.products'))
            ->assertOk()
            ->assertJsonPath('site_id', $stockedSite->id)
            ->assertJsonPath('products.0.current_branch_stock.available', 7);
    }

    private function adminUser(): User
    {
        $role = Role::create([
            'name' => 'POS Sync Administrator',
            'permissions' => ['*'],
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function site(string $name, string $code): Site
    {
        return Site::create([
            'name' => $name,
            'code' => $code,
            'type' => 'branch',
            'is_active' => true,
        ]);
    }
}
