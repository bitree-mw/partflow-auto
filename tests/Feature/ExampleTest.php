<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\BusinessSetting;
use App\Models\CarMake;
use App\Models\CarModel;
use App\Models\Contact;
use App\Models\InventoryDocument;
use App\Models\PartType;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\User;
use App\Models\UserSiteAccess;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('web.dashboard'));
    }

    public function test_guest_back_office_pages_redirect_to_login(): void
    {
        $this->get('/back-office/dashboard')->assertRedirect(route('login'));
        $this->get('/pos')->assertRedirect(route('login'));
    }

    public function test_back_office_pages_return_successful_responses(): void
    {
        $this->actingAs(User::factory()->create());

        $pages = [
            '/pos',
            '/back-office/dashboard',
            '/back-office/sales',
            '/back-office/purchases',
            '/back-office/purchases/create',
            '/back-office/customers',
            '/back-office/customers/create',
            '/back-office/suppliers',
            '/back-office/suppliers/create',
            '/back-office/reports',
            '/back-office/alerts',
            '/back-office/settings',
            '/back-office/catalog/car-models',
            '/back-office/catalog/car-models/create',
            '/back-office/catalog/brands',
            '/back-office/catalog/brands/create',
            '/back-office/catalog/part-types',
            '/back-office/catalog/part-types/create',
            '/back-office/catalog/fuel-types',
            '/back-office/catalog/fuel-types/create',
            '/back-office/catalog/products',
            '/back-office/catalog/products/create',
        ];

        foreach ($pages as $page) {
            $this->get($page)->assertOk();
        }
    }

    public function test_user_can_login_and_logout_with_session_api_token(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@partflow.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->post(route('login.store'), [
            'email' => 'admin@partflow.test',
            'password' => 'password',
        ])
            ->assertRedirect(route('web.dashboard'))
            ->assertSessionHas('partflow_api_token')
            ->assertSessionHas('partflow_api_token_id');

        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_user_can_login_with_username(): void
    {
        $user = User::factory()->create([
            'username' => 'frontcounter',
            'email' => 'frontcounter@partflow.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->post(route('login.store'), [
            'login' => 'frontcounter',
            'password' => 'password',
        ])
            ->assertRedirect(route('web.dashboard'))
            ->assertSessionHas('partflow_api_token')
            ->assertSessionHas('partflow_api_token_id');

        $this->assertAuthenticatedAs($user);
    }

    public function test_payment_accounts_web_flow_uses_api_backed_records(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('web.payment-accounts.index'))
            ->assertOk()
            ->assertDontSee('Payment account display samples');

        $this->post(route('web.payment-accounts.store'), [
            'account_name' => 'API Till',
            'account_type' => 'cash',
            'bank_name' => 'Main counter',
            'account_number' => 'TILL-01',
            'account_holder_name' => 'Cashier Desk',
            'is_active' => 1,
        ])->assertRedirect();

        $paymentAccount = PaymentAccount::where('account_name', 'API Till')->firstOrFail();

        $this->get(route('web.payment-accounts.show', $paymentAccount))
            ->assertOk()
            ->assertSee('API Till');

        $this->put(route('web.payment-accounts.update', $paymentAccount), [
            'account_name' => 'API Till Updated',
            'account_type' => 'mobile_money',
            'bank_name' => 'Airtel Money',
            'mobile_number' => '+265991000200',
            'account_holder_name' => 'Sales Desk',
            'is_active' => 1,
        ])->assertRedirect(route('web.payment-accounts.show', $paymentAccount));

        $this->assertDatabaseHas(PaymentAccount::class, [
            'id' => $paymentAccount->id,
            'account_name' => 'API Till Updated',
            'account_type' => 'mobile_money',
        ]);

        $this->delete(route('web.payment-accounts.destroy', $paymentAccount))
            ->assertRedirect(route('web.payment-accounts.index'));

        $this->assertSoftDeleted(PaymentAccount::class, [
            'id' => $paymentAccount->id,
        ]);
    }

    public function test_admin_settings_persist_and_add_entities_from_dialog_actions(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('web.settings.update'), [
            'settings_action' => 'save_settings',
            'settings_panel' => 'operating-defaults',
            'business_name' => 'PartFlow Test Auto',
            'legal_name' => 'PartFlow Test Auto Limited',
            'registration_number' => 'MW-TEST-001',
            'base_country' => 'Malawi',
            'base_currency' => 'MWK',
            'default_branch' => 'All sites',
            'stock_costing_method' => 'Weighted average cost',
            'low_stock_policy' => 'Warn before checkout',
        ])->assertRedirect(route('web.settings.index').'#operating-defaults');

        $this->assertSame('PartFlow Test Auto', BusinessSetting::where('key', 'business_name')->firstOrFail()->value);

        $this->post(route('web.settings.update'), [
            'settings_action' => 'create_site',
            'settings_panel' => 'company-sites',
            'site_name' => 'Settings Branch',
            'site_type' => 'branch',
            'site_city' => 'Lilongwe',
            'site_country' => 'Malawi',
        ])->assertRedirect(route('web.settings.index').'#company-sites');

        $site = Site::where('name', 'Settings Branch')->firstOrFail();

        $this->get(route('web.settings.index').'#company-sites')
            ->assertOk()
            ->assertSee('Settings Branch');

        $this->post(route('web.settings.update'), [
            'settings_action' => 'create_document_series',
            'settings_panel' => 'document-numbering',
            'series_name' => 'Supplier returns',
            'series_prefix' => 'SRN',
            'series_next_number' => 1001,
        ])->assertRedirect(route('web.settings.index').'#document-numbering');

        $this->get(route('web.settings.index').'#document-numbering')
            ->assertOk()
            ->assertSee('Supplier returns')
            ->assertSee('SRN');

        $this->post(route('web.settings.update'), [
            'settings_action' => 'create_user',
            'settings_panel' => 'user-management',
            'user_name' => 'Settings Manager',
            'user_email' => 'settings-manager@example.test',
            'user_role' => 'Settings Role',
            'user_site' => $site->name,
            'user_password' => 'password123',
        ])->assertRedirect(route('web.settings.index').'#user-management');

        $role = Role::where('name', 'Settings Role')->firstOrFail();
        $user = User::where('email', 'settings-manager@example.test')->firstOrFail();

        $this->assertSame($role->id, $user->role_id);
        $this->assertDatabaseHas(UserSiteAccess::class, [
            'user_id' => $user->id,
            'site_id' => $site->id,
            'is_default' => true,
        ]);
    }

    public function test_catalogue_can_create_brand_part_type_and_product(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('web.catalog.brands.store'), [
            'name' => 'Test Brand',
            'code' => 'TB',
            'country' => 'Malawi',
            'description' => 'A test catalogue brand.',
        ])->assertRedirect(route('web.catalog.brands.index'));

        $brand = Brand::where('code', 'TB')->firstOrFail();

        $this->post(route('web.catalog.part-types.store'), [
            'name' => 'Water Pump',
            'code' => 'WP',
            'description' => 'Cooling system water pumps.',
        ])->assertRedirect(route('web.catalog.part-types.index'));

        $partType = PartType::where('code', 'WP')->firstOrFail();
        $make = CarMake::create([
            'name' => 'Toyota',
            'code' => 'TY',
            'country' => 'Japan',
            'is_active' => true,
        ]);
        $model = VehicleModel::create([
            'car_make_id' => $make->id,
            'name' => 'Corolla',
            'code' => 'CO',
            'is_active' => true,
        ]);
        $carModel = CarModel::create([
            'car_make_id' => $make->id,
            'vehicle_model_id' => $model->id,
            'make' => 'Toyota',
            'make_code' => 'TY',
            'model' => 'Corolla',
            'model_code' => 'CO',
            'year' => 2016,
            'engine_size' => '1.6L',
            'variant_name' => 'Sedan',
            'country_of_origin' => 'Japan',
            'is_active' => true,
        ]);

        $this->post(route('web.catalog.products.store'), [
            'car_model_id' => $carModel->id,
            'part_type_id' => $partType->id,
            'brand_id' => $brand->id,
            'part_country_of_origin' => 'Japan',
            'default_purchase_price' => 45000,
            'default_selling_price' => 68000,
            'default_low_stock_level' => 3,
            'pack_size' => 1,
        ])->assertRedirect(route('web.catalog.products.index'));

        $product = Product::where('brand_id', $brand->id)->firstOrFail();

        $this->assertSame('WPTYCO1616', $product->product_code);
        $this->assertSame('Toyota Corolla 2016 1.6L Sedan Water Pump', $product->product_name);

        $this->delete(route('web.catalog.part-types.destroy', $partType))
            ->assertRedirect(route('web.catalog.part-types.index'))
            ->assertSessionHas('error');

        $this->delete(route('web.catalog.car-models.destroy', $carModel))
            ->assertRedirect(route('web.catalog.car-models.index'))
            ->assertSessionHas('error');

        $this->assertTrue($partType->fresh()->is_active);
        $this->assertTrue($carModel->fresh()->is_active);
    }

    public function test_catalogue_can_create_product_without_vehicle_fitment(): void
    {
        $this->actingAs(User::factory()->create());

        $brand = Brand::create([
            'name' => 'Universal Brand',
            'code' => 'UB',
            'is_active' => true,
        ]);
        $partType = PartType::create([
            'name' => 'Cleaning Cloth',
            'code' => 'CC',
            'is_active' => true,
        ]);

        $this->post(route('web.catalog.products.store'), [
            'part_type_id' => $partType->id,
            'brand_id' => $brand->id,
            'part_country_of_origin' => 'Malawi',
            'default_selling_price' => 2500,
            'default_low_stock_level' => 5,
            'pack_size' => 1,
        ])->assertRedirect(route('web.catalog.products.index'));

        $product = Product::where('brand_id', $brand->id)->firstOrFail();

        $this->assertNull($product->car_model_id);
        $this->assertSame('CC-UBXX-MWI-001', $product->product_code);
        $this->assertSame('Universal Brand Cleaning Cloth (MWI)', $product->product_name);
        $this->assertSame(0, $product->compatibilities()->count());
    }

    public function test_contacts_and_purchase_workflows_create_real_records(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('web.customers.store'), [
            'name' => 'Test Customer Garage',
            'email' => 'customer@example.test',
            'credit_limit' => 250000,
        ])->assertRedirect(route('web.customers.index'));

        $this->post(route('web.suppliers.store'), [
            'name' => 'Test Supplier Depot',
            'email' => 'supplier@example.test',
            'credit_limit' => 500000,
        ])->assertRedirect(route('web.suppliers.index'));

        $supplier = Contact::where('email', 'supplier@example.test')->firstOrFail();
        $site = Site::create([
            'name' => 'Main Warehouse',
            'code' => 'MWH',
            'type' => 'warehouse',
            'is_active' => true,
        ]);
        $make = CarMake::create([
            'name' => 'Nissan',
            'code' => 'NS',
            'country' => 'Japan',
            'is_active' => true,
        ]);
        $model = VehicleModel::create([
            'car_make_id' => $make->id,
            'name' => 'Tiida',
            'code' => 'NT',
            'is_active' => true,
        ]);
        $carModel = CarModel::create([
            'car_make_id' => $make->id,
            'vehicle_model_id' => $model->id,
            'make' => 'Nissan',
            'make_code' => 'NS',
            'model' => 'Tiida',
            'model_code' => 'NT',
            'year' => 2014,
            'is_active' => true,
        ]);
        $partType = PartType::create([
            'name' => 'Oil Filter',
            'code' => 'OF',
            'is_active' => true,
        ]);
        $product = Product::create([
            'product_code' => 'NSNT14OF',
            'product_name' => 'Nissan Tiida Oil Filter',
            'car_model_id' => $carModel->id,
            'part_type_id' => $partType->id,
            'default_purchase_price' => 10000,
            'default_selling_price' => 15000,
            'default_low_stock_level' => 2,
            'is_active' => true,
        ]);
        $account = PaymentAccount::create([
            'account_name' => 'Main Cash',
            'account_type' => 'cash',
            'is_active' => true,
        ]);

        $this->post(route('web.purchases.store'), [
            'contact_id' => $supplier->id,
            'destination_site_id' => $site->id,
            'document_date' => '2026-06-28',
            'status' => 'completed',
            'items' => [
                1 => [
                    'product_id' => $product->id,
                    'quantity' => 4,
                    'unit_cost' => 11000,
                ],
            ],
            'payment_account_id' => $account->id,
            'amount_paid' => 22000,
            'payment_method' => 'cash',
        ])->assertRedirect(route('web.purchases.index'));

        $purchase = InventoryDocument::where('document_type', 'purchase')->firstOrFail();

        $this->assertSame('partial', $purchase->payment_status);
        $this->assertEquals(44000, (float) $purchase->total_amount);
        $this->assertEquals(4, SiteStock::where('product_id', $product->id)->where('site_id', $site->id)->value('quantity_on_hand'));

        $this->delete(route('web.catalog.products.destroy', $product))
            ->assertRedirect(route('web.catalog.products.index'))
            ->assertSessionHas('error');

        $this->assertTrue($product->fresh()->is_active);

        $this->delete(route('web.suppliers.destroy', $supplier))
            ->assertRedirect(route('web.suppliers.index'))
            ->assertSessionHas('error');

        $this->assertTrue($supplier->fresh()->is_active);

        InventoryDocument::create([
            'document_number' => 'SALE-TEST-BAL',
            'document_type' => 'sale',
            'contact_id' => Contact::where('email', 'customer@example.test')->firstOrFail()->id,
            'source_site_id' => $site->id,
            'document_date' => '2026-06-28',
            'status' => 'completed',
            'subtotal_amount' => 25000,
            'total_amount' => 25000,
            'paid_amount' => 5000,
            'balance_amount' => 20000,
            'payment_status' => 'partial',
            'created_by' => $user->id,
        ]);

        $customer = Contact::where('email', 'customer@example.test')->firstOrFail();

        $this->delete(route('web.customers.destroy', $customer))
            ->assertRedirect(route('web.customers.index'))
            ->assertSessionHas('error');

        $this->assertTrue($customer->fresh()->is_active);

        $this->post(route('web.pos.sales'), [
            'source_site_id' => $site->id,
            'cart_payload' => json_encode([
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 16000],
            ]),
            'payment_account_id' => $account->id,
            'amount_paid' => 16000,
        ])->assertRedirect(route('web.pos'));

        $sale = InventoryDocument::where('document_type', 'sale')->latest('id')->firstOrFail();

        $this->assertSame('paid', $sale->payment_status);
        $this->assertEquals(16000, (float) $sale->total_amount);
        $this->assertEquals(3, SiteStock::where('product_id', $product->id)->where('site_id', $site->id)->value('quantity_on_hand'));
    }
}
