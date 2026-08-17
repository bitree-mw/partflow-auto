<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\User;
use App\Models\UserSiteAccess;
use App\Repositories\DashboardRepository;
use App\Repositories\ReportRepository;
use App\Services\AlertService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HighestPriorityGuardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_permissions_limit_pages_navigation_and_api_endpoints(): void
    {
        $cashier = $this->userWithPermissions([
            'pos.use',
            'sales.create',
            'sales.view',
            'customers.view',
            'payment-accounts.view',
        ]);

        $this->actingAs($cashier);

        $this->get(route('web.dashboard'))->assertOk();
        $this->get(route('web.pos'))
            ->assertOk()
            ->assertDontSee('>Hold<', false)
            ->assertDontSee('>Discount<', false);
        $this->get(route('web.sales.index'))->assertOk();
        $this->get(route('web.settings.index'))
            ->assertForbidden()
            ->assertSee('This page is not available for your role.');
        $this->get(route('web.purchases.create'))->assertForbidden();
        $this->get(route('web.catalog.products.create'))->assertForbidden();

        $navigation = $this->get(route('web.dashboard'));
        $navigation->assertSee('Point of sale');
        $navigation->assertSee('Customers');
        $navigation->assertDontSee('Purchases');
        $navigation->assertDontSee('Parts catalogue');
        $navigation->assertDontSee('Admin settings');
        $navigation->assertDontSee('Add part');
        $navigation->assertDontSee('Open reports');
        $navigation->assertDontSee('View payment report');

        Sanctum::actingAs($cashier);
        $this->getJson('/api/roles')->assertForbidden();
    }

    public function test_pos_rejects_invalid_quantities_and_uses_the_catalogue_price(): void
    {
        $cashier = $this->userWithPermissions(['sales.create']);
        $site = Site::query()->create([
            'name' => 'Sales Site',
            'code' => 'SALE',
            'type' => 'shop',
            'is_active' => true,
        ]);
        $type = ProductType::query()->create([
            'name' => 'Test Part',
            'code' => 'TP',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'product_code' => 'TP-UNKN-UNK-001',
            'product_name' => 'Test Part',
            'product_type_id' => $type->id,
            'default_selling_price' => 15000,
            'is_active' => true,
        ]);
        $stock = SiteStock::query()->create([
            'product_id' => $product->id,
            'site_id' => $site->id,
            'quantity_on_hand' => 5,
            'reserved_quantity' => 0,
            'low_stock_level' => 0,
        ]);
        UserSiteAccess::query()->create([
            'user_id' => $cashier->id,
            'site_id' => $site->id,
            'access_level' => 'sales',
            'can_make_sales' => true,
            'is_active' => true,
        ]);

        $this->actingAs($cashier);

        $this->postJson(route('web.pos.sales'), [
            'source_site_id' => $site->id,
            'cart_payload' => json_encode([
                ['product_id' => $product->id, 'quantity' => -1, 'unit_price' => -500],
            ]),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.quantity');

        $this->assertDatabaseCount('inventory_documents', 0);

        $this->postJson(route('web.pos.sales'), [
            'source_site_id' => $site->id,
            'cart_payload' => json_encode([
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1],
            ]),
        ])->assertCreated();

        $this->assertDatabaseHas('inventory_documents', [
            'document_type' => 'sale',
            'subtotal_amount' => 15000,
            'total_amount' => 15000,
        ]);
        $this->assertDatabaseHas('inventory_document_items', [
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 15000,
        ]);
        $this->assertSame(4, $stock->fresh()->quantity_on_hand);
    }

    public function test_zero_reorder_threshold_disables_low_stock_alerts(): void
    {
        $site = Site::query()->create([
            'name' => 'Alert Site',
            'code' => 'ALRT',
            'type' => 'warehouse',
            'is_active' => true,
        ]);
        $type = ProductType::query()->create([
            'name' => 'Alert Part',
            'code' => 'AP',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'product_code' => 'AP-UNKN-UNK-001',
            'product_name' => 'Alert Part',
            'product_type_id' => $type->id,
            'default_low_stock_level' => 0,
            'is_active' => true,
        ]);
        $stock = SiteStock::query()->create([
            'product_id' => $product->id,
            'site_id' => $site->id,
            'quantity_on_hand' => 0,
            'reserved_quantity' => 0,
            'low_stock_level' => 0,
        ]);

        $this->assertSame(0, app(AlertService::class)->summary()['count']);
        $this->assertSame(0, app(DashboardRepository::class)->lowStockCount());
        $this->assertCount(0, app(ReportRepository::class)->lowStockBySite());

        app(ProductService::class)->update($product, ['default_low_stock_level' => 2]);

        $this->assertSame(2, $stock->fresh()->low_stock_level);
        $this->assertSame(1, app(AlertService::class)->summary()['count']);
        $this->assertSame(1, app(DashboardRepository::class)->lowStockCount());
        $this->assertCount(1, app(ReportRepository::class)->lowStockBySite());
    }

    private function userWithPermissions(array $permissions): User
    {
        $role = Role::query()->create([
            'name' => 'Role '.str()->random(8),
            'permissions' => $permissions,
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
