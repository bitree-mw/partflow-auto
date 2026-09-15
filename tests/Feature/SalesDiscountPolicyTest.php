<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\User;
use App\Repositories\DashboardRepository;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesDiscountPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_products_default_the_minimum_price_to_twenty_percent_below_selling_price(): void
    {
        $productType = $this->productType();

        $product = app(ProductService::class)->create([
            'product_code' => 'DISC-PRODUCT-001',
            'product_name' => 'Discount Product',
            'product_type_id' => $productType->id,
            'default_selling_price' => 1250,
        ]);

        $this->assertSame('1000.00', $product->minimum_selling_price);
    }

    public function test_pos_accepts_discount_at_the_effective_limit_and_uses_trusted_product_pricing(): void
    {
        [$user, $site, $product] = $this->posContext();

        $this->actingAs($user)
            ->withSession(['pos_site_id' => $site->id])
            ->postJson(route('web.pos.sales'), [
                'source_site_id' => $site->id,
                'cart_payload' => json_encode([[
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 1,
                ]]),
                'discount_amount' => 200,
                'amount_paid' => 0,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('inventory_documents', [
            'document_type' => 'sale',
            'subtotal_amount' => 1000,
            'discount_amount' => 200,
            'total_amount' => 800,
        ]);
        $this->assertDatabaseHas('inventory_document_items', [
            'product_id' => $product->id,
            'unit_price' => 1000,
            'discount_amount' => 200,
            'line_total' => 800,
        ]);
    }

    public function test_pos_rejects_a_discount_beyond_the_admin_cap(): void
    {
        BusinessSetting::create(['key' => 'maximum_discount_percentage', 'value' => 10]);
        [$user, $site, $product] = $this->posContext();

        $this->actingAs($user)
            ->postJson(route('web.pos.sales'), [
                'source_site_id' => $site->id,
                'cart_payload' => json_encode([[
                    'product_id' => $product->id,
                    'quantity' => 1,
                ]]),
                'discount_amount' => 100.01,
                'amount_paid' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('discount_amount');

        $this->assertDatabaseCount('inventory_documents', 0);
        $this->assertSame(5, $product->siteStocks()->firstOrFail()->quantity_on_hand);
    }

    public function test_api_sales_cannot_bypass_the_product_floor_with_submitted_prices_or_line_discounts(): void
    {
        [$user, $site, $product] = $this->posContext();
        Sanctum::actingAs($user);

        $this->postJson('/api/sales', [
            'source_site_id' => $site->id,
            'status' => 'completed',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_cost' => 0,
                'unit_price' => 5000,
                'discount_amount' => 200.01,
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.discount_amount');

        $this->assertDatabaseCount('inventory_documents', 0);
    }

    public function test_inventory_value_uses_the_stricter_admin_cap_and_product_floor(): void
    {
        [, $site, $product] = $this->posContext();
        $repository = app(DashboardRepository::class);

        $this->assertSame(4500.0, $repository->totalStockValue($site->id, null, 10));
        $this->assertSame(4000.0, $repository->totalStockValue($site->id, null, 30));

        BusinessSetting::create(['key' => 'maximum_discount_percentage', 'value' => 10]);
        $this->actingAs($this->adminUser())
            ->get(route('web.dashboard'))
            ->assertOk()
            ->assertSee('Lowest authorized value (max 10.00%)')
            ->assertSee('MWK 5K');
    }

    public function test_product_settings_and_pos_render_discount_controls(): void
    {
        [$user, $site] = $this->posContext();

        $this->actingAs($user)
            ->withSession(['pos_site_id' => $site->id])
            ->get(route('web.pos'))
            ->assertOk()
            ->assertSee('data-pos-discount-amount', false)
            ->assertSee('data-discount-percentage', false);

        $this->actingAs($this->adminUser())
            ->get(route('web.catalog.products.create'))
            ->assertOk()
            ->assertSee('name="minimum_selling_price"', false);

        $this->get(route('web.settings.index'))
            ->assertOk()
            ->assertSee('name="maximum_discount_percentage"', false);
    }

    public function test_admin_can_save_the_maximum_discount_percentage(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('web.settings.business-information.update'), [
                'business_name' => 'PartFlow Auto',
                'base_currency' => 'MWK',
                'maximum_discount_percentage' => 12.5,
                'settings_panel' => 'operating-defaults',
            ])
            ->assertRedirect(route('web.settings.index').'#operating-defaults');

        $this->assertSame(
            12.5,
            BusinessSetting::query()->where('key', 'maximum_discount_percentage')->firstOrFail()->value
        );
    }

    private function posContext(): array
    {
        $role = Role::create([
            'name' => 'Discount Test Administrator '.Role::query()->count(),
            'permissions' => ['*'],
            'is_active' => true,
        ]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $site = Site::create([
            'name' => 'Discount Test Branch '.Site::query()->count(),
            'code' => 'DTB'.Site::query()->count(),
            'type' => 'branch',
            'is_active' => true,
        ]);
        $product = Product::create([
            'product_code' => 'DISC-'.str_pad((string) Product::query()->count(), 3, '0', STR_PAD_LEFT),
            'product_name' => 'Discount Test Part',
            'product_type_id' => $this->productType()->id,
            'default_purchase_price' => 600,
            'default_selling_price' => 1000,
            'minimum_selling_price' => 800,
            'is_active' => true,
        ]);

        SiteStock::create([
            'site_id' => $site->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 5,
            'reserved_quantity' => 0,
        ]);

        return [$user, $site, $product];
    }

    private function productType(): ProductType
    {
        return ProductType::query()->firstOrCreate(
            ['code' => 'DISC'],
            ['name' => 'Discount Test Parts', 'is_active' => true]
        );
    }

    private function adminUser(): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'System Administrator'],
            ['permissions' => ['*'], 'is_active' => true]
        );

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }
}
