<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\User;
use App\Services\PackageService;
use App\Services\SystemConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionPackageTest extends TestCase
{
    use RefreshDatabase;

    private const SUADMIN_PASSWORD = 'Package-Test-Password-1';

    public function test_installations_default_to_autopilot_with_every_feature(): void
    {
        $packages = app(PackageService::class);

        $this->assertSame('autopilot', $packages->currentKey());

        foreach (array_keys(config('packages.feature_labels')) as $feature) {
            $this->assertTrue($packages->has($feature), "Autopilot should include {$feature}.");
        }
    }

    public function test_package_feature_matrix_matches_the_price_list(): void
    {
        $expected = [
            'ignition' => [],
            'drive' => ['multi_branch', 'stock_transfers', 'branch_access', 'customer_balances', 'expenses', 'low_stock_emails', 'custom_branding'],
            'overdrive' => ['multi_branch', 'stock_transfers', 'branch_access', 'customer_balances', 'expenses', 'low_stock_emails', 'custom_branding', 'vehicle_fitment_search', 'csv_exports'],
            'autopilot' => ['multi_branch', 'stock_transfers', 'branch_access', 'customer_balances', 'expenses', 'low_stock_emails', 'custom_branding', 'vehicle_fitment_search', 'csv_exports', 'support_247'],
        ];

        foreach ($expected as $key => $features) {
            $this->assertEqualsCanonicalizing($features, config("packages.packages.{$key}.features"), $key);
        }

        $this->assertSame([15000, 20000, 45000, 60000], array_column(config('packages.packages'), 'monthly_price'));
    }

    public function test_super_admin_changes_the_package_and_ignition_requires_a_single_active_branch(): void
    {
        $first = $this->site('Main Branch', 'MAIN');
        $second = $this->site('Second Branch', 'SEC');
        $this->signInSuperAdmin();

        $this->get(route('suadmin.package.edit'))->assertOk()->assertSee('Autopilot')->assertSee('MK15,000');

        $this->put(route('suadmin.package.update'), ['package' => 'ignition'])
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'one active branch'));
        $this->assertSame('autopilot', app(PackageService::class)->currentKey());

        $second->update(['is_active' => false]);

        $this->put(route('suadmin.package.update'), ['package' => 'ignition'])->assertSessionHas('success');
        $this->assertSame('ignition', BusinessSetting::query()->where('key', 'subscription_package')->value('value'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'package.changed', 'actor_type' => 'super_admin']);

        // A second active branch cannot be added or reactivated on Ignition.
        $this->post(route('suadmin.sites.store'), ['name' => 'Third Branch', 'type' => 'branch'])
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'one active branch'));
        $this->assertDatabaseMissing('sites', ['name' => 'Third Branch']);
        $this->patch(route('suadmin.sites.status', $second), ['is_active' => 1])->assertSessionHas('error');
        $this->assertFalse($second->refresh()->is_active);
        $this->assertTrue($first->refresh()->is_active);
    }

    public function test_package_change_rejects_unknown_packages(): void
    {
        $this->signInSuperAdmin();

        $this->put(route('suadmin.package.update'), ['package' => 'turbo'])->assertSessionHasErrors('package');
        $this->assertSame('autopilot', app(PackageService::class)->currentKey());
    }

    public function test_ignition_hides_and_blocks_transfers_branch_access_and_expenses(): void
    {
        $this->usePackage('ignition');
        $site = $this->site('Only Branch', 'ONLY');
        $admin = $this->administrator();

        $this->actingAs($admin)->get(route('web.catalog.sites.index'))->assertOk()->assertDontSee('Transfer stock');
        $this->actingAs($admin)->get(route('web.catalog.sites.transfers.index'))->assertForbidden();

        Sanctum::actingAs($admin);
        $this->getJson('/api/transfers')->assertForbidden()->assertJsonPath('success', false)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Upgrade to Drive'));
        $this->getJson('/api/user-site-accesses')->assertForbidden();
        $this->getJson('/api/expenses')->assertForbidden();
        $this->getJson('/api/reports/customer-balances')->assertForbidden();
        $this->getJson('/api/sites')->assertOk();
        $this->assertNotNull($site);
    }

    public function test_ignition_staff_work_at_the_single_branch_without_site_assignments(): void
    {
        $this->usePackage('ignition');
        $site = $this->site('Only Branch', 'ONLY');
        $role = Role::query()->create(['name' => 'Cashier', 'permissions' => ['sales.create', 'stock.view'], 'is_active' => true]);
        $cashier = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        Sanctum::actingAs($cashier);
        $this->getJson('/api/sites')->assertOk()->assertJsonFragment(['id' => $site->id]);

        // Drive and higher keep explicit branch access.
        $this->usePackage('drive');
        $this->getJson('/api/sites')->assertOk()->assertJsonMissing(['id' => $site->id]);
    }

    public function test_ignition_sales_must_be_paid_in_full(): void
    {
        $this->usePackage('ignition');
        [$user, $site, $product, $account] = $this->posContext();

        $this->actingAs($user)->withSession(['pos_site_id' => $site->id])
            ->get(route('web.pos'))
            ->assertOk()
            ->assertSee('Sales must be paid in full');

        $this->actingAs($user)->withSession(['pos_site_id' => $site->id])
            ->postJson(route('web.pos.sales'), $this->salePayload($site, $product, $account, 400))
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount_paid');
        $this->assertDatabaseCount('inventory_documents', 0);
        $this->assertSame(5, SiteStock::query()->sole()->quantity_on_hand);

        $this->actingAs($user)->withSession(['pos_site_id' => $site->id])
            ->postJson(route('web.pos.sales'), $this->salePayload($site, $product, $account, 1000))
            ->assertCreated();
        $this->assertDatabaseHas('inventory_documents', ['document_type' => 'sale', 'balance_amount' => 0]);
    }

    public function test_drive_allows_credit_sales(): void
    {
        $this->usePackage('drive');
        [$user, $site, $product, $account] = $this->posContext();

        $this->actingAs($user)->withSession(['pos_site_id' => $site->id])
            ->postJson(route('web.pos.sales'), $this->salePayload($site, $product, $account, 400))
            ->assertCreated();
        $this->assertDatabaseHas('inventory_documents', ['document_type' => 'sale', 'balance_amount' => 600]);
    }

    public function test_ignition_hides_customer_balances_and_debtor_reports(): void
    {
        $this->usePackage('ignition');
        $this->site('Only Branch', 'ONLY');
        $admin = $this->administrator();

        $this->actingAs($admin)->get(route('web.customers.index'))
            ->assertOk()
            ->assertDontSee('Customer balance')
            ->assertDontSee('Credit outstanding');
        $this->actingAs($admin)->get(route('web.reports.index'))
            ->assertOk()
            ->assertDontSee('Debtors report')
            ->assertSee('Creditors report');
        $this->actingAs($admin)->get(route('web.reports.view', ['report_type' => 'debtor-balances']))
            ->assertSessionHasErrors('report_type');
        $this->actingAs($admin)->get(route('web.dashboard'))->assertOk()->assertDontSee('Outstanding debt');
    }

    public function test_csv_exports_need_overdrive_or_higher(): void
    {
        $this->site('Only Branch', 'ONLY');
        $admin = $this->administrator();

        $this->usePackage('drive');
        $this->actingAs($admin)->get(route('web.reports.index'))->assertOk()->assertDontSee('Download full CSV');
        $this->actingAs($admin)->get(route('web.reports.export', ['report_type' => 'sales']))->assertForbidden();

        $this->usePackage('overdrive');
        $this->actingAs($admin)->get(route('web.reports.index'))->assertOk()->assertSee('Download full CSV');
        $this->actingAs($admin)->get(route('web.reports.export', ['report_type' => 'sales']))->assertOk();
    }

    public function test_vehicle_fitment_search_needs_overdrive_or_higher(): void
    {
        $this->usePackage('drive');
        [$user, $site] = $this->posContext();

        $this->actingAs($user)->withSession(['pos_site_id' => $site->id])
            ->get(route('web.pos'))
            ->assertOk()
            ->assertSee('data-pos-vehicle-picker  hidden style="display: none"', false)
            ->assertSee('Search product, code, barcode, or OEM');
        $this->actingAs($user)->getJson(route('web.pos.vehicle-models'))->assertForbidden();

        $this->usePackage('overdrive');
        $this->actingAs($user)->withSession(['pos_site_id' => $site->id])
            ->get(route('web.pos'))
            ->assertOk()
            ->assertDontSee('hidden style="display: none"', false);
    }

    public function test_branding_and_low_stock_emails_are_kept_but_inactive_on_ignition(): void
    {
        BusinessSetting::query()->create(['key' => 'primary_color', 'value' => '#123456']);
        BusinessSetting::query()->create(['key' => 'low_stock_notification_email', 'value' => 'stock@example.test']);
        $this->site('Only Branch', 'ONLY');
        $admin = $this->administrator();

        $this->usePackage('ignition');
        $configuration = app(SystemConfigurationService::class);
        $this->assertSame('#0a1630', $configuration->headerContext()['primary_color']);
        $this->assertNull($configuration->lowStockNotificationEmail());

        // Saving settings on Ignition must not overwrite the stored branding or recipient.
        $this->actingAs($admin)->post(route('web.settings.business-information.update'), [
            'business_name' => 'Ignition Motors',
            'base_currency' => 'MWK',
            'primary_color' => '#000000',
            'low_stock_notification_email' => '',
        ]);
        $this->assertSame('Ignition Motors', BusinessSetting::query()->where('key', 'business_name')->value('value'));
        $this->assertSame('#123456', BusinessSetting::query()->where('key', 'primary_color')->value('value'));
        $this->assertSame('stock@example.test', BusinessSetting::query()->where('key', 'low_stock_notification_email')->value('value'));

        $this->actingAs($admin)->get(route('web.settings.index'))
            ->assertOk()
            ->assertSee('Ignition')
            ->assertDontSee('Low-stock notification email');

        $this->usePackage('drive');
        $this->assertSame('#123456', app(SystemConfigurationService::class)->headerContext()['primary_color']);
        $this->assertSame('stock@example.test', app(SystemConfigurationService::class)->lowStockNotificationEmail());
    }

    public function test_autopilot_admins_see_the_support_contact_set_in_suadmin(): void
    {
        $this->site('Only Branch', 'ONLY');
        $admin = $this->administrator();
        $this->signInSuperAdmin();

        $this->put(route('suadmin.package.support'), [
            'support_phone' => '+265 999 123 456',
            'support_whatsapp' => '+265 888 123 456',
            'support_email' => 'help@partflow.test',
            'support_hours' => 'Available 24/7',
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('audit_logs', ['event' => 'package.support_updated']);

        $this->actingAs($admin)->get(route('web.settings.index'))
            ->assertOk()
            ->assertSee('24/7 support')
            ->assertSee('help@partflow.test')
            ->assertSee('https://wa.me/265888123456', false);

        $this->usePackage('overdrive');
        $this->actingAs($admin)->get(route('web.settings.index'))->assertOk()->assertDontSee('help@partflow.test');
    }

    private function usePackage(string $key): void
    {
        BusinessSetting::query()->updateOrCreate(['key' => 'subscription_package'], ['value' => $key]);
        // The package is memoised per request; direct service calls in a test share one request object.
        app('request')->attributes->remove('partflow.package');
    }

    private function signInSuperAdmin(): void
    {
        config(['suadmin.password_hash' => Hash::make(self::SUADMIN_PASSWORD)]);
        $this->post(route('suadmin.login.store'), ['password' => self::SUADMIN_PASSWORD])->assertRedirect(route('suadmin.dashboard'));
    }

    private function administrator(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'System Administrator'], ['permissions' => ['*'], 'is_active' => true]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function site(string $name, string $code): Site
    {
        return Site::query()->create(['name' => $name, 'code' => $code, 'type' => 'branch', 'is_active' => true]);
    }

    private function posContext(): array
    {
        $user = $this->administrator();
        $site = $this->site('Package Branch', 'PKG');
        $type = ProductType::query()->firstOrCreate(['code' => 'PKG'], ['name' => 'Package parts', 'is_active' => true]);
        $product = Product::query()->create([
            'product_code' => 'PKG-001',
            'product_name' => 'Package Test Part',
            'product_type_id' => $type->id,
            'default_purchase_price' => 600,
            'default_selling_price' => 1000,
            'minimum_selling_price' => 800,
            'is_active' => true,
        ]);
        SiteStock::query()->create(['site_id' => $site->id, 'product_id' => $product->id, 'quantity_on_hand' => 5, 'reserved_quantity' => 0]);
        $account = PaymentAccount::query()->create(['account_name' => 'Main Cash', 'account_type' => 'cash', 'is_active' => true]);

        return [$user, $site, $product, $account];
    }

    private function salePayload(Site $site, Product $product, PaymentAccount $account, float $amountPaid): array
    {
        return [
            'source_site_id' => $site->id,
            'cart_payload' => json_encode([['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000]]),
            'discount_amount' => 0,
            'payment_account_id' => $account->id,
            'amount_paid' => $amountPaid,
        ];
    }
}
