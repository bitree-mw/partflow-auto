<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\User;
use App\Repositories\DashboardRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTodaySalesFromPosTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_completed_pos_sale_immediately_updates_today_sales_for_the_selected_branch(): void
    {
        $role = Role::create(['name' => 'Dashboard Sales Administrator', 'permissions' => ['*'], 'is_active' => true]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $site = Site::create(['name' => 'Dashboard POS Branch', 'code' => 'DPB', 'type' => 'branch', 'is_active' => true]);
        $productType = ProductType::create(['name' => 'Dashboard POS Parts', 'code' => 'DPP', 'is_active' => true]);
        $product = Product::create([
            'product_code' => 'DASH-POS-001',
            'product_name' => 'Dashboard POS Part',
            'product_type_id' => $productType->id,
            'default_purchase_price' => 1000,
            'default_selling_price' => 1500,
            'is_active' => true,
        ]);

        SiteStock::create([
            'site_id' => $site->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 2,
            'reserved_quantity' => 0,
        ]);

        $this->actingAs($user)
            ->withSession(['pos_site_id' => $site->id])
            ->postJson(route('web.pos.sales'), [
                'source_site_id' => $site->id,
                'cart_payload' => json_encode([
                    ['product_id' => $product->id, 'quantity' => 1],
                ]),
                'amount_paid' => 0,
            ])
            ->assertCreated();

        $this->assertSame(1500.0, app(DashboardRepository::class)->todaySales($site->id));

        $this->get(route('web.dashboard'))
            ->assertOk()
            ->assertSee('Today sales')
            ->assertSee('MWK 2K')
            ->assertSee('data-dashboard-live', false)
            ->assertSee(route('web.dashboard.live'), false);

        $this->getJson(route('web.dashboard.live'))
            ->assertOk()
            ->assertJsonPath('today_sales', 'MWK 2K')
            ->assertJsonPath('today_sale_count', 1)
            ->assertJsonPath('average_sale', 'MWK 2K');
    }
}
