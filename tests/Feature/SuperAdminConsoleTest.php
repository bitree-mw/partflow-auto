<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\InventoryDocument;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class SuperAdminConsoleTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Correct-Horse-Battery-9!';

    protected function setUp(): void
    {
        parent::setUp();

        config(['suadmin.password_hash' => Hash::make(self::PASSWORD)]);
    }

    public function test_console_requires_the_super_admin_password(): void
    {
        $this->get(route('suadmin.dashboard'))->assertRedirect(route('suadmin.login'));
        $this->get(route('suadmin.sites.index'))->assertRedirect(route('suadmin.login'));

        $this->post(route('suadmin.login.store'), ['password' => 'wrong-password'])
            ->assertRedirect(route('suadmin.login'))
            ->assertSessionHasErrors('password');

        $this->get(route('suadmin.dashboard'))->assertRedirect(route('suadmin.login'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'suadmin.login.failed', 'actor_type' => 'guest']);

        $this->post(route('suadmin.login.store'), ['password' => self::PASSWORD])
            ->assertRedirect(route('suadmin.dashboard'));

        $this->get(route('suadmin.dashboard'))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Super admin console');
        $this->assertDatabaseHas('audit_logs', ['event' => 'suadmin.login.succeeded', 'actor_type' => 'super_admin']);

        $this->post(route('suadmin.logout'))->assertRedirect(route('suadmin.login'));
        $this->get(route('suadmin.dashboard'))->assertRedirect(route('suadmin.login'));
    }

    public function test_a_staff_administrator_session_does_not_grant_console_access(): void
    {
        $this->actingAs($this->administrator())
            ->get(route('suadmin.dashboard'))
            ->assertRedirect(route('suadmin.login'));
    }

    public function test_console_is_disabled_without_a_configured_hash_and_rejects_a_malformed_one(): void
    {
        config(['suadmin.password_hash' => '']);

        $this->get(route('suadmin.login'))->assertOk()->assertSee('Not configured');
        $this->post(route('suadmin.login.store'), ['password' => self::PASSWORD])->assertSessionHasErrors('password');

        config(['suadmin.password_hash' => 'not-a-bcrypt-hash']);
        $this->post(route('suadmin.login.store'), ['password' => 'not-a-bcrypt-hash'])->assertSessionHasErrors('password');
        $this->get(route('suadmin.dashboard'))->assertRedirect(route('suadmin.login'));
    }

    public function test_console_session_expires_after_inactivity(): void
    {
        $this->signIn();
        $this->get(route('suadmin.dashboard'))->assertOk();

        $this->travel(31)->minutes();

        $this->get(route('suadmin.dashboard'))->assertRedirect(route('suadmin.login'));
    }

    public function test_login_is_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('suadmin.login.store'), ['password' => "wrong-{$attempt}"]);
        }

        $this->post(route('suadmin.login.store'), ['password' => self::PASSWORD])->assertStatus(429);
    }

    public function test_super_admin_creates_edits_and_deletes_sites_with_audit_entries(): void
    {
        $this->signIn();

        $this->post(route('suadmin.sites.store'), [
            'name' => 'Kanengo Warehouse',
            'type' => 'warehouse',
            'location' => 'Lilongwe',
            'phone' => '+265 999 000 111',
            'address' => 'Area 28, Plot 12',
        ])->assertRedirect(route('suadmin.sites.index'));

        $site = Site::query()->where('name', 'Kanengo Warehouse')->firstOrFail();
        $this->assertSame('KANENG', $site->code);
        $this->assertDatabaseHas('audit_logs', ['event' => 'site.created', 'subject_id' => $site->id, 'actor_type' => 'super_admin']);

        $this->put(route('suadmin.sites.update', $site), [
            'name' => 'Kanengo Main Warehouse',
            'code' => 'kan-wh',
            'type' => 'warehouse',
            'location' => 'Lilongwe',
        ])->assertRedirect(route('suadmin.sites.index'));

        $this->assertSame('KAN-WH', $site->refresh()->code);
        $update = AuditLog::query()->where('event', 'site.updated')->sole();
        $this->assertSame(['from' => 'Kanengo Warehouse', 'to' => 'Kanengo Main Warehouse'], $update->properties['changes']['name']);

        $this->get(route('suadmin.sites.index'))->assertOk()->assertSee('Kanengo Main Warehouse');

        $this->delete(route('suadmin.sites.destroy', $site))->assertRedirect(route('suadmin.sites.index'));
        $this->assertSoftDeleted($site);
        $this->assertDatabaseHas('audit_logs', ['event' => 'site.deleted', 'subject_id' => $site->id]);
    }

    public function test_sites_holding_stock_or_open_documents_cannot_be_deactivated_or_deleted(): void
    {
        $site = $this->site('Blantyre Branch', 'BLBR');
        $stock = $this->stockAt($site, onHand: 3, reserved: 0);

        $this->signIn();

        $this->patch(route('suadmin.sites.status', $site), ['is_active' => 0])
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'still has stock'));
        $this->delete(route('suadmin.sites.destroy', $site))->assertSessionHas('error');
        $this->assertTrue($site->refresh()->is_active);
        $this->assertNotSoftDeleted($site);

        $stock->update(['quantity_on_hand' => 0, 'reserved_quantity' => 2]);
        $this->patch(route('suadmin.sites.status', $site), ['is_active' => 0])->assertSessionHas('error');

        $stock->update(['reserved_quantity' => 0]);
        InventoryDocument::query()->create([
            'document_number' => 'PUR-OPEN-1',
            'document_type' => 'purchase',
            'destination_site_id' => $site->id,
            'document_date' => now(),
            'status' => 'pending',
            'created_by' => $this->administrator()->id,
        ]);

        $this->patch(route('suadmin.sites.status', $site), ['is_active' => 0])
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'draft or pending'));

        InventoryDocument::query()->update(['status' => 'cancelled']);

        $this->patch(route('suadmin.sites.status', $site), ['is_active' => 0])->assertSessionHas('success');
        $this->assertFalse($site->refresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['event' => 'site.deactivated', 'subject_id' => $site->id]);
    }

    public function test_normal_administrator_edits_and_toggles_sites_but_cannot_create_them(): void
    {
        $admin = $this->administrator();
        $site = $this->site('Zomba Shop', 'ZMBSHP');
        $stock = $this->stockAt($site, onHand: 4, reserved: 0);

        $this->actingAs($admin);

        $this->get('/back-office/catalog/sites/create')->assertNotFound();
        $this->post('/back-office/catalog/sites', ['name' => 'Sneaky Site', 'type' => 'shop'])->assertStatus(405);
        $this->post(route('web.settings.update'), ['settings_action' => 'create_site', 'site_name' => 'Sneaky Site', 'site_type' => 'shop'])
            ->assertSessionHas('error');
        $this->assertDatabaseMissing('sites', ['name' => 'Sneaky Site']);

        $this->get(route('web.catalog.sites.index'))
            ->assertOk()
            ->assertDontSee('Add site')
            ->assertSee(route('web.catalog.sites.edit', $site), false);

        $this->put(route('web.catalog.sites.update', $site), [
            'name' => 'Zomba Main Shop',
            'code' => 'ZMBSHP',
            'type' => 'shop',
            'location' => 'Zomba',
            'phone' => '+265 1 000 000',
        ])->assertRedirect(route('web.catalog.sites.index'));
        $this->assertSame('Zomba Main Shop', $site->refresh()->name);
        $this->assertDatabaseHas('audit_logs', ['event' => 'site.updated', 'actor_user_id' => $admin->id]);

        $this->patch(route('web.catalog.sites.status', $site), ['is_active' => 0])->assertSessionHas('error');
        $this->assertTrue($site->refresh()->is_active);

        $stock->update(['quantity_on_hand' => 0]);
        $this->patch(route('web.catalog.sites.status', $site), ['is_active' => 0])->assertSessionHas('success');
        $this->assertFalse($site->refresh()->is_active);

        // Inactive sites stay visible to administrators so they can be reactivated.
        $this->get(route('web.catalog.sites.index'))->assertOk()->assertSee('Zomba Main Shop');
        $this->patch(route('web.catalog.sites.status', $site), ['is_active' => 1])->assertSessionHas('success');
        $this->assertTrue($site->refresh()->is_active);
    }

    public function test_staff_without_settings_permission_cannot_edit_sites(): void
    {
        $site = $this->site('Mzuzu Branch', 'MZBR');
        $role = Role::query()->create(['name' => 'Stock viewer', 'permissions' => ['stock.view'], 'is_active' => true]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $this->actingAs($user)->get(route('web.catalog.sites.edit', $site))->assertForbidden();
        $this->actingAs($user)->patch(route('web.catalog.sites.status', $site), ['is_active' => 0])->assertForbidden();
    }

    public function test_api_site_writes_are_closed(): void
    {
        $site = $this->site('Api Branch', 'APIBR');
        Sanctum::actingAs($this->administrator());

        $this->postJson('/api/sites', ['name' => 'Api Created', 'code' => 'APIC', 'type' => 'shop'])->assertStatus(405);
        $this->putJson("/api/sites/{$site->id}", ['name' => 'Renamed'])->assertStatus(405);
        $this->deleteJson("/api/sites/{$site->id}")->assertStatus(405);
        $this->getJson("/api/sites/{$site->id}")->assertOk();
        $this->assertSame('Api Branch', $site->refresh()->name);
    }

    public function test_super_admin_manages_user_and_admin_accounts(): void
    {
        $existingAdmin = $this->administrator();
        $adminRole = $existingAdmin->role;
        $cashierRole = Role::query()->create(['name' => 'Cashier', 'permissions' => ['pos.use'], 'is_active' => true]);
        $site = $this->site('Lilongwe Branch', 'LLBR');

        $this->signIn();

        $this->post(route('suadmin.users.store'), [
            'name' => 'Chisomo Banda',
            'email' => 'Chisomo@Example.test',
            'role_id' => $adminRole->id,
            'site' => $site->name,
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
        ])->assertRedirect(route('suadmin.users.index'));

        $newAdmin = User::query()->where('email', 'chisomo@example.test')->firstOrFail();
        $this->assertSame($adminRole->id, $newAdmin->role_id);
        $this->assertSame('chisomo', $newAdmin->username);
        $this->assertTrue(Hash::check('secret-pass-1', $newAdmin->password));

        $created = AuditLog::query()->where('event', 'user.created')->sole();
        $this->assertSame('super_admin', $created->actor_type);
        $this->assertArrayNotHasKey('password', $created->properties);

        $this->put(route('suadmin.users.update', $newAdmin), [
            'name' => 'Chisomo Banda',
            'email' => 'chisomo@example.test',
            'role_id' => $cashierRole->id,
            'site' => 'All sites',
            'password' => 'new-secret-pass',
            'password_confirmation' => 'new-secret-pass',
            'is_active' => 1,
        ])->assertRedirect(route('suadmin.users.index'));

        $this->assertSame($cashierRole->id, $newAdmin->refresh()->role_id);
        $this->assertTrue(Hash::check('new-secret-pass', $newAdmin->password));
        $changes = AuditLog::query()->where('event', 'user.updated')->sole()->properties['changes'];
        $this->assertSame('changed', $changes['password']);
        $this->assertSame(['from' => $adminRole->name, 'to' => 'Cashier'], $changes['role']);
        $this->assertStringNotContainsString('new-secret-pass', json_encode($changes));

        // The last active administrator is protected.
        $this->patch(route('suadmin.users.deactivate', $existingAdmin))
            ->assertSessionHas('error', 'At least one active system administrator must remain.');
        $this->assertTrue($existingAdmin->refresh()->is_active);

        $this->patch(route('suadmin.users.deactivate', $newAdmin))->assertSessionHas('success');
        $this->assertFalse($newAdmin->refresh()->is_active);

        $this->get(route('suadmin.users.index'))->assertOk()->assertSee('Chisomo Banda')->assertSee('Administrator');
    }

    public function test_staff_sign_ins_and_settings_changes_are_audited_and_listed(): void
    {
        $admin = $this->administrator(['username' => 'thoko', 'password' => Hash::make('staff-pass-1')]);

        $this->post(route('login.store'), ['login' => 'thoko', 'password' => 'wrong'])->assertSessionHasErrors();
        $this->post(route('login.store'), ['login' => 'thoko', 'password' => 'staff-pass-1'])->assertRedirect();

        $this->post(route('web.settings.business-information.update'), [
            'business_name' => 'Audited Auto Parts',
            'base_currency' => 'MWK',
            'settings_panel' => 'company-profile',
        ]);
        $this->post(route('logout'));

        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login.failed', 'subject_id' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login.succeeded', 'actor_user_id' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'settings.updated', 'actor_user_id' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.logout', 'actor_user_id' => $admin->id]);

        $this->signIn();

        $this->get(route('suadmin.audit-logs.index', ['group' => 'settings']))
            ->assertOk()
            ->assertSee('settings.updated')
            ->assertSee('Audited Auto Parts')
            ->assertDontSee('auth.login.failed');

        $this->get(route('suadmin.audit-logs.index', ['group' => 'auth']))
            ->assertOk()
            ->assertSee('auth.login.failed');
    }

    public function test_audit_entries_cannot_be_changed_or_deleted(): void
    {
        $this->post(route('suadmin.login.store'), ['password' => 'wrong']);
        $entry = AuditLog::query()->firstOrFail();

        $this->expectException(LogicException::class);
        $entry->update(['description' => 'tampered']);
    }

    private function signIn(): void
    {
        $this->post(route('suadmin.login.store'), ['password' => self::PASSWORD])->assertRedirect(route('suadmin.dashboard'));
    }

    private function administrator(array $attributes = []): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'System Administrator'],
            ['permissions' => ['*'], 'is_active' => true]
        );

        return User::factory()->create([...$attributes, 'role_id' => $role->id, 'is_active' => true]);
    }

    private function site(string $name, string $code): Site
    {
        return Site::query()->create(['name' => $name, 'code' => $code, 'type' => 'branch', 'is_active' => true]);
    }

    private function stockAt(Site $site, int $onHand, int $reserved): SiteStock
    {
        $type = ProductType::query()->firstOrCreate(['code' => 'TST'], ['name' => 'Test part', 'is_active' => true]);
        $product = Product::query()->create([
            'product_code' => 'TST-'.$site->code,
            'product_name' => 'Test part '.$site->code,
            'product_type_id' => $type->id,
            'default_selling_price' => 1000,
            'default_low_stock_level' => 0,
            'is_active' => true,
        ]);

        return SiteStock::query()->create([
            'site_id' => $site->id,
            'product_id' => $product->id,
            'quantity_on_hand' => $onHand,
            'reserved_quantity' => $reserved,
            'low_stock_level' => 0,
        ]);
    }
}
