<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\BusinessSetting;
use App\Models\CarMake;
use App\Models\CarModel;
use App\Models\Contact;
use App\Models\InventoryDocument;
use App\Models\InventoryDocumentItem;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\StockMovement;
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
        $this->actingAs($this->adminUser());

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
            '/back-office/catalog/product-types',
            '/back-office/catalog/product-types/create',
            '/back-office/catalog/fuel-types',
            '/back-office/catalog/fuel-types/create',
            '/back-office/catalog/products',
            '/back-office/catalog/products/create',
        ];

        foreach ($pages as $page) {
            $this->get($page)->assertOk();
        }
    }

    public function test_dashboard_uses_the_prototype_metric_summary_and_selected_sections(): void
    {
        $user = $this->adminUser();
        $site = Site::create([
            'name' => 'Dashboard Branch',
            'code' => 'DSH',
            'type' => 'warehouse',
            'is_active' => true,
        ]);

        InventoryDocument::create([
            'document_number' => 'SALE-DASHBOARD-CURRENT',
            'document_type' => 'sale',
            'source_site_id' => $site->id,
            'document_date' => today(),
            'status' => 'completed',
            'subtotal_amount' => 2000,
            'total_amount' => 2000,
            'paid_amount' => 2000,
            'balance_amount' => 0,
            'payment_status' => 'paid',
            'created_by' => $user->id,
        ]);
        InventoryDocument::create([
            'document_number' => 'SALE-DASHBOARD-PREVIOUS',
            'document_type' => 'sale',
            'source_site_id' => $site->id,
            'document_date' => today()->subMonthNoOverflow(),
            'status' => 'completed',
            'subtotal_amount' => 1000,
            'total_amount' => 1000,
            'paid_amount' => 1000,
            'balance_amount' => 0,
            'payment_status' => 'paid',
            'created_by' => $user->id,
        ]);
        InventoryDocument::create([
            'document_number' => 'PURCHASE-DASHBOARD-PENDING',
            'document_type' => 'purchase',
            'destination_site_id' => $site->id,
            'document_date' => today(),
            'status' => 'pending',
            'subtotal_amount' => 5000,
            'total_amount' => 5000,
            'paid_amount' => 0,
            'balance_amount' => 5000,
            'payment_status' => 'unpaid',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('web.dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'Inventory value',
                'Low stock items',
                'Sales this month',
                'Pending orders',
                'Today sales',
                'Today profit',
                'Outstanding debt',
                'Out of stock',
            ])
            ->assertSee('100.0% increase from last month')
            ->assertSee('1 order awaiting action')
            ->assertSee('Average sale value')
            ->assertSee('Inventory value outlook')
            ->assertSee('Debtors and creditors')
            ->assertDontSee('Fast-moving parts')
            ->assertDontSee('Recent activity');

        $this->actingAs($user)
            ->get(route('web.dashboard', ['site_id' => $site->id, 'revenue_period' => 30]))
            ->assertOk()
            ->assertSee('data-revenue-days="30"', false)
            ->assertSee('--chart-columns: 30', false)
            ->assertSee('value="30" selected', false);
    }

    public function test_reports_page_and_full_csv_exports_use_the_selected_filters(): void
    {
        $user = $this->adminUser();
        $site = Site::create([
            'name' => 'Reporting Branch',
            'code' => 'RPT',
            'type' => 'warehouse',
            'is_active' => true,
        ]);
        $productType = ProductType::create([
            'name' => 'Reporting Part',
            'code' => 'RPTP',
            'is_active' => true,
        ]);
        $product = Product::create([
            'product_code' => 'RPT-001',
            'product_name' => 'Reporting Test Part',
            'product_type_id' => $productType->id,
            'default_purchase_price' => 100,
            'default_selling_price' => 175,
            'is_active' => true,
        ]);
        $customer = Contact::create([
            'contact_type' => 'customer',
            'code' => 'C-RPT',
            'name' => 'Reporting Customer',
            'is_active' => true,
        ]);
        $supplier = Contact::create([
            'contact_type' => 'supplier',
            'code' => 'S-RPT',
            'name' => 'Reporting Supplier',
            'is_active' => true,
        ]);

        SiteStock::create([
            'site_id' => $site->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 4,
            'reserved_quantity' => 0,
        ]);

        foreach (['SALE-RPT-001', 'SALE-RPT-002'] as $index => $number) {
            $sale = InventoryDocument::create([
                'document_number' => $number,
                'document_type' => 'sale',
                'contact_id' => $customer->id,
                'source_site_id' => $site->id,
                'document_date' => today()->subDays($index),
                'status' => 'completed',
                'subtotal_amount' => 175,
                'total_amount' => 175,
                'paid_amount' => $index === 0 ? 75 : 175,
                'balance_amount' => $index === 0 ? 100 : 0,
                'payment_status' => $index === 0 ? 'partial' : 'paid',
                'created_by' => $user->id,
            ]);
            InventoryDocumentItem::create([
                'inventory_document_id' => $sale->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_cost' => 100,
                'unit_price' => 175,
                'line_total' => 175,
                'profit_amount' => 75,
            ]);
        }

        InventoryDocument::create([
            'document_number' => 'SALE-RPT-OUTSIDE',
            'document_type' => 'sale',
            'contact_id' => $customer->id,
            'source_site_id' => $site->id,
            'document_date' => today()->subDays(20),
            'status' => 'completed',
            'subtotal_amount' => 175,
            'total_amount' => 175,
            'paid_amount' => 175,
            'balance_amount' => 0,
            'payment_status' => 'paid',
            'created_by' => $user->id,
        ]);

        $purchase = InventoryDocument::create([
            'document_number' => 'PURCHASE-RPT-001',
            'document_type' => 'purchase',
            'contact_id' => $supplier->id,
            'destination_site_id' => $site->id,
            'document_date' => today(),
            'status' => 'completed',
            'subtotal_amount' => 400,
            'total_amount' => 400,
            'paid_amount' => 100,
            'balance_amount' => 300,
            'payment_status' => 'partial',
            'created_by' => $user->id,
        ]);
        InventoryDocumentItem::create([
            'inventory_document_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'unit_cost' => 100,
            'unit_price' => 175,
            'line_total' => 400,
        ]);

        $this->actingAs($user)
            ->get(route('web.reports.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'Sales report',
                'Purchase report',
                'Inventory report',
                'Creditors report',
                'Debtors report',
            ])
            ->assertDontSee('P&amp;L movement accounts', false)
            ->assertDontSee('Profit and loss');

        $filters = [
            'date_from' => today()->subDays(2)->toDateString(),
            'date_to' => today()->toDateString(),
            'site_id' => $site->id,
        ];

        $salesCsv = $this->get(route('web.reports.export', [...$filters, 'report_type' => 'sales']));
        $salesCsv->assertOk()->assertDownload();
        $salesContent = $salesCsv->streamedContent();
        $this->assertStringContainsString('SALE-RPT-001', $salesContent);
        $this->assertStringContainsString('SALE-RPT-002', $salesContent);
        $this->assertStringNotContainsString('SALE-RPT-OUTSIDE', $salesContent);

        $inventoryCsv = $this->get(route('web.reports.export', [...$filters, 'report_type' => 'inventory-valuation']));
        $inventoryContent = $inventoryCsv->streamedContent();
        $this->assertStringContainsString('unit_purchase_cost', $inventoryContent);
        $this->assertStringContainsString('potential_sales_value', $inventoryContent);
        $this->assertStringContainsString('Reporting Test Part', $inventoryContent);

        $creditorContent = $this->get(route('web.reports.export', [...$filters, 'report_type' => 'creditor-balances']))->streamedContent();
        $this->assertStringContainsString('Reporting Supplier', $creditorContent);
        $this->assertStringContainsString('PURCHASE-RPT-001', $creditorContent);

        $debtorContent = $this->get(route('web.reports.export', [...$filters, 'report_type' => 'debtor-balances']))->streamedContent();
        $this->assertStringContainsString('Reporting Customer', $debtorContent);
        $this->assertStringContainsString('SALE-RPT-001', $debtorContent);
    }

    public function test_purchase_form_orders_lines_payment_totals_and_status_by_workflow(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('web.purchases.create'))
            ->assertOk()
            ->assertSeeInOrder([
                'data-purchase-lines',
                'id="amount_paid"',
                'data-purchase-subtotal',
                'id="status"',
                'Save purchase',
            ], false)
            ->assertSee('purchase-line-label', false)
            ->assertSee('purchase-submit-bar', false);
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
        $this->actingAs($this->adminUser());

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

        $this->get(route('web.payment-accounts.edit', $paymentAccount))
            ->assertOk()
            ->assertSee('data-confirm-title="Save account changes?"', false)
            ->assertSee('data-confirm-label="Save changes"', false);

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
        $this->actingAs($this->adminUser());

        $this->get(route('web.settings.index'))
            ->assertOk()
            ->assertSee('data-confirm-title="Save car make changes?"', false)
            ->assertSee('data-confirm-title="Save settings changes?"', false);

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

    public function test_catalogue_can_create_brand_product_type_and_product(): void
    {
        $this->actingAs($this->adminUser());

        $this->post(route('web.catalog.brands.store'), [
            'name' => 'Test Brand',
            'code' => 'TB',
            'country' => 'Malawi',
            'description' => 'A test catalogue brand.',
        ])->assertRedirect(route('web.catalog.brands.index'));

        $brand = Brand::where('code', 'TB')->firstOrFail();

        $this->post(route('web.catalog.product-types.store'), [
            'name' => 'Water Pump',
            'code' => 'WP',
            'description' => 'Cooling system water pumps.',
        ])->assertRedirect(route('web.catalog.product-types.index'));

        $productType = ProductType::where('code', 'WP')->firstOrFail();
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
            'product_type_id' => $productType->id,
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
        $this->assertSame('Malawi', $product->part_country_of_origin);

        $this->delete(route('web.catalog.product-types.destroy', $productType))
            ->assertRedirect(route('web.catalog.product-types.index'))
            ->assertSessionHas('error');

        $this->delete(route('web.catalog.car-models.destroy', $carModel))
            ->assertRedirect(route('web.catalog.car-models.index'))
            ->assertSessionHas('error');

        $this->assertTrue($productType->fresh()->is_active);
        $this->assertTrue($carModel->fresh()->is_active);
    }

    public function test_catalogue_can_create_product_without_vehicle_fitment(): void
    {
        $this->actingAs($this->adminUser());

        $brand = Brand::create([
            'name' => 'Universal Brand',
            'code' => 'UB',
            'country' => 'Malawi',
            'is_active' => true,
        ]);
        $productType = ProductType::create([
            'name' => 'Cleaning Cloth',
            'code' => 'CC',
            'is_active' => true,
        ]);

        $this->post(route('web.catalog.products.store'), [
            'product_type_id' => $productType->id,
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

    public function test_unknown_brand_keeps_manually_selected_product_origin(): void
    {
        $this->actingAs($this->adminUser());

        $productType = ProductType::create([
            'name' => 'Universal Clip',
            'code' => 'UC',
            'is_active' => true,
        ]);

        $this->post(route('web.catalog.products.store'), [
            'product_type_id' => $productType->id,
            'part_country_of_origin' => 'Malawi',
            'default_selling_price' => 1000,
            'default_low_stock_level' => 2,
            'pack_size' => 1,
        ])->assertRedirect(route('web.catalog.products.index'));

        $product = Product::query()->where('product_type_id', $productType->id)->firstOrFail();

        $this->assertSame('UNKN', $product->brand?->code);
        $this->assertSame('Malawi', $product->part_country_of_origin);
    }

    public function test_product_forms_use_confirmation_for_edits_and_deactivation(): void
    {
        $this->actingAs($this->adminUser());

        $productType = ProductType::create([
            'name' => 'Brake Pad',
            'code' => 'BP',
            'is_active' => true,
        ]);
        $product = Product::create([
            'product_code' => 'BP-UNKN-UNK-001',
            'product_name' => 'Brake Pad',
            'product_type_id' => $productType->id,
            'default_selling_price' => 25000,
            'is_active' => true,
        ]);

        $this->get(route('web.catalog.products.create'))
            ->assertOk()
            ->assertSee('class="form-field product-type-field"', false)
            ->assertSee('data-product-type-picker', false)
            ->assertSee('data-product-brand', false)
            ->assertSee('data-product-origin', false);

        $this->get(route('web.catalog.products.edit', $product))
            ->assertOk()
            ->assertSee('data-confirm-title="Save product changes?"', false)
            ->assertSee('data-confirm-label="Save changes"', false);

        $this->get(route('web.catalog.products.index'))
            ->assertOk()
            ->assertSee('data-confirm-title="Deactivate product?"', false)
            ->assertSee('data-confirm-label="Deactivate"', false);
    }

    public function test_catalogue_shows_prototype_stock_statuses_and_tints_inactive_products(): void
    {
        $this->actingAs($this->adminUser());

        $site = Site::create([
            'name' => 'Catalogue Warehouse',
            'code' => 'CAT',
            'type' => 'warehouse',
            'is_active' => true,
        ]);
        $productType = ProductType::create([
            'name' => 'Catalogue Status Part',
            'code' => 'CSP',
            'is_active' => true,
        ]);

        $products = collect([
            ['code' => 'CSP-001', 'name' => 'A In Stock Part', 'quantity' => 20, 'active' => true],
            ['code' => 'CSP-002', 'name' => 'B Low Stock Part', 'quantity' => 7, 'active' => true],
            ['code' => 'CSP-003', 'name' => 'C Critical Part', 'quantity' => 4, 'active' => true],
            ['code' => 'CSP-004', 'name' => 'D Inactive Part', 'quantity' => 20, 'active' => false],
        ])->map(function (array $row) use ($productType, $site): Product {
            $product = Product::create([
                'product_code' => $row['code'],
                'product_name' => $row['name'],
                'product_type_id' => $productType->id,
                'default_selling_price' => 10000,
                'default_low_stock_level' => 10,
                'is_active' => $row['active'],
            ]);

            SiteStock::create([
                'site_id' => $site->id,
                'product_id' => $product->id,
                'quantity_on_hand' => $row['quantity'],
                'reserved_quantity' => 0,
                'low_stock_level' => 10,
            ]);

            return $product;
        });

        $response = $this->get(route('web.catalog.products.index'));

        $response
            ->assertOk()
            ->assertSeeInOrder(['In stock', 'Low stock', 'Critical', 'Inactive'])
            ->assertSee('product-row-inactive', false)
            ->assertSee('status-pill success', false)
            ->assertSee('status-pill warning', false)
            ->assertSee('status-pill danger', false)
            ->assertSee('status-pill inactive', false)
            ->assertSee('Low stock level: 10');

        $this->assertCount(4, $products);
    }

    public function test_stock_take_requires_and_records_a_reason_for_stock_adjustments(): void
    {
        $user = $this->adminUser();
        $this->actingAs($user);

        $site = Site::create([
            'name' => 'Stock Take Warehouse',
            'code' => 'STW',
            'type' => 'warehouse',
            'is_active' => true,
        ]);
        $productType = ProductType::create([
            'name' => 'Engine Oil',
            'code' => 'EOIL',
            'is_active' => true,
        ]);
        $product = Product::create([
            'product_code' => 'EOIL-GEN-MWI-001',
            'product_name' => 'Engine Oil',
            'product_type_id' => $productType->id,
            'default_selling_price' => 25000,
            'is_active' => true,
        ]);

        SiteStock::create([
            'site_id' => $site->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 5,
            'reserved_quantity' => 0,
        ]);

        $this->post(route('web.catalog.sites.stock-takes.store'), [
            'site_id' => $site->id,
            'document_date' => '2026-07-20',
            'items' => [[
                'product_id' => $product->id,
                'counted_quantity' => 7,
            ]],
        ])->assertSessionHasErrors('items.0.notes');

        $this->assertSame(5, SiteStock::where('site_id', $site->id)->where('product_id', $product->id)->value('quantity_on_hand'));
        $this->assertDatabaseCount('inventory_documents', 0);

        $reason = 'Two sealed containers found in the receiving area.';

        $this->post(route('web.catalog.sites.stock-takes.store'), [
            'site_id' => $site->id,
            'document_date' => '2026-07-20',
            'items' => [[
                'product_id' => $product->id,
                'counted_quantity' => 7,
                'notes' => $reason,
            ]],
        ])->assertRedirect();

        $stockTake = InventoryDocument::where('document_type', 'stock_take')->firstOrFail();
        $item = $stockTake->items()->firstOrFail();
        $movement = StockMovement::where('inventory_document_id', $stockTake->id)->firstOrFail();

        $this->assertSame(7, SiteStock::where('site_id', $site->id)->where('product_id', $product->id)->value('quantity_on_hand'));
        $this->assertSame($reason, $item->notes);
        $this->assertSame($reason, $movement->notes);
        $this->assertSame(2, $movement->quantity_change);

        $this->get(route('web.catalog.sites.stock-takes.show', $stockTake))
            ->assertOk()
            ->assertSee($reason);
    }

    public function test_contacts_and_purchase_workflows_create_real_records(): void
    {
        $user = $this->adminUser();
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
        $productType = ProductType::create([
            'name' => 'Oil Filter',
            'code' => 'OF',
            'is_active' => true,
        ]);
        $product = Product::create([
            'product_code' => 'NSNT14OF',
            'product_name' => 'Nissan Tiida Oil Filter',
            'car_model_id' => $carModel->id,
            'product_type_id' => $productType->id,
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
            'amount_paid' => 15000,
        ])->assertRedirect(route('web.pos'));

        $sale = InventoryDocument::where('document_type', 'sale')->latest('id')->firstOrFail();

        $this->assertSame('paid', $sale->payment_status);
        $this->assertEquals(15000, (float) $sale->total_amount);
        $this->assertEquals(3, SiteStock::where('product_id', $product->id)->where('site_id', $site->id)->value('quantity_on_hand'));
    }

    public function test_transaction_pages_use_the_branch_selected_in_the_session(): void
    {
        $user = $this->adminUser();
        $selectedSite = Site::create([
            'name' => 'Selected Branch',
            'code' => 'SEL',
            'type' => 'branch',
            'is_active' => true,
        ]);
        $otherSite = Site::create([
            'name' => 'Other Branch',
            'code' => 'OTH',
            'type' => 'branch',
            'is_active' => true,
        ]);

        InventoryDocument::create([
            'document_number' => 'SALE-SELECTED-001',
            'document_type' => 'sale',
            'source_site_id' => $selectedSite->id,
            'document_date' => now(),
            'status' => 'completed',
            'total_amount' => 12000,
            'created_by' => $user->id,
        ]);
        InventoryDocument::create([
            'document_number' => 'SALE-OTHER-001',
            'document_type' => 'sale',
            'source_site_id' => $otherSite->id,
            'document_date' => now(),
            'status' => 'completed',
            'total_amount' => 8000,
            'created_by' => $user->id,
        ]);
        InventoryDocument::create([
            'document_number' => 'PURCHASE-SELECTED-001',
            'document_type' => 'purchase',
            'destination_site_id' => $selectedSite->id,
            'document_date' => now(),
            'status' => 'completed',
            'total_amount' => 16000,
            'created_by' => $user->id,
        ]);
        InventoryDocument::create([
            'document_number' => 'PURCHASE-OTHER-001',
            'document_type' => 'purchase',
            'destination_site_id' => $otherSite->id,
            'document_date' => now(),
            'status' => 'completed',
            'total_amount' => 6000,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession(['pos_site_id' => $selectedSite->id])
            ->get(route('web.sales.index'))
            ->assertOk()
            ->assertSee('SALE-SELECTED-001')
            ->assertDontSee('SALE-OTHER-001');

        $this->get(route('web.purchases.index'))
            ->assertOk()
            ->assertSee('PURCHASE-SELECTED-001')
            ->assertDontSee('PURCHASE-OTHER-001');

        $this->get(route('web.purchases.create'))
            ->assertOk()
            ->assertSee('value="'.$selectedSite->id.'" selected', false);
    }

    private function adminUser(array $attributes = []): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'Test Administrator'],
            ['permissions' => ['*'], 'is_active' => true],
        );

        return User::factory()->create(array_merge([
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));
    }
}
