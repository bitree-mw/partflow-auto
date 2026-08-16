<?php

namespace Tests\Feature;

use App\Models\InventoryDocument;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\User;
use App\Models\UserSiteAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteAccessAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_endpoints_only_return_records_from_active_assigned_sites(): void
    {
        $user = $this->userWithPermissions(['stock.view', 'reports.view', 'sales.view']);
        $assignedSite = $this->site('Assigned', 'ASGN');
        $unassignedSite = $this->site('Unassigned', 'UNAS');
        $inactiveSite = $this->site('Inactive', 'INAC', false);
        $product = $this->product();

        $this->assign($user, $assignedSite);
        $this->assign($user, $inactiveSite);

        foreach ([$assignedSite, $unassignedSite, $inactiveSite] as $site) {
            SiteStock::query()->create([
                'product_id' => $product->id,
                'site_id' => $site->id,
                'quantity_on_hand' => 5,
                'reserved_quantity' => 0,
            ]);
        }

        foreach ([[$assignedSite, 100], [$unassignedSite, 900], [$inactiveSite, 500]] as [$site, $total]) {
            InventoryDocument::query()->create([
                'document_number' => "SALE-{$site->code}",
                'document_type' => 'sale',
                'source_site_id' => $site->id,
                'document_date' => now(),
                'status' => 'completed',
                'total_amount' => $total,
                'created_by' => $user->id,
            ]);
        }

        Sanctum::actingAs($user);

        $this->getJson('/api/sites')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assignedSite->id);

        $this->getJson('/api/site-stocks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.site_id', $assignedSite->id);

        $this->getJson('/api/reports/current-stock-by-site')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.site_id', $assignedSite->id);

        $this->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.today_sales', 100);

        $this->getJson("/api/site-stocks?site_id={$unassignedSite->id}")
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_operation_flags_gate_writes_even_when_the_site_is_assigned(): void
    {
        $user = $this->userWithPermissions([
            'sales.create',
            'purchases.create',
            'stock.transfer',
            'stock.adjust',
        ]);
        $site = $this->site('Limited', 'LIMT');
        $destination = $this->site('Destination', 'DEST');
        $product = $this->product();
        $access = $this->assign($user, $site);
        $this->assign($user, $destination, ['can_transfer_stock' => false]);
        $stock = SiteStock::query()->create([
            'product_id' => $product->id,
            'site_id' => $site->id,
            'quantity_on_hand' => 10,
            'reserved_quantity' => 0,
        ]);

        Sanctum::actingAs($user);

        $salePayload = [
            'source_site_id' => $site->id,
            'status' => 'completed',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
            ]],
        ];

        $this->postJson('/api/sales', $salePayload)->assertForbidden();
        $this->postJson('/api/purchases', [
            'destination_site_id' => $site->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertForbidden();
        $this->postJson('/api/transfers', [
            'source_site_id' => $site->id,
            'destination_site_id' => $destination->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertForbidden();
        $this->postJson('/api/stock-adjustments', [
            'site_id' => $site->id,
            'items' => [['product_id' => $product->id, 'quantity_change' => 1]],
        ])->assertForbidden();

        $access->update(['can_make_sales' => true]);

        $this->postJson('/api/sales', $salePayload)
            ->assertCreated()
            ->assertJsonPath('data.source_site_id', $site->id);
        $this->assertSame(9, $stock->fresh()->quantity_on_hand);
    }

    public function test_system_administrator_can_use_any_active_site_without_assignments(): void
    {
        $admin = $this->userWithPermissions(['*']);
        $activeSite = $this->site('Active', 'ACTV');
        $inactiveSite = $this->site('Inactive', 'INAC', false);
        $product = $this->product();

        SiteStock::query()->create([
            'product_id' => $product->id,
            'site_id' => $activeSite->id,
            'quantity_on_hand' => 3,
            'reserved_quantity' => 0,
        ]);
        SiteStock::query()->create([
            'product_id' => $product->id,
            'site_id' => $inactiveSite->id,
            'quantity_on_hand' => 3,
            'reserved_quantity' => 0,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/site-stocks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.site_id', $activeSite->id);

        $this->postJson('/api/sales', [
            'source_site_id' => $activeSite->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->postJson('/api/sales', [
            'source_site_id' => $inactiveSite->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertForbidden();
    }

    public function test_transfer_reads_require_access_to_both_sites(): void
    {
        $user = $this->userWithPermissions(['stock.view']);
        $assignedSite = $this->site('Assigned', 'ASGN');
        $unassignedSite = $this->site('Unassigned', 'UNAS');
        $this->assign($user, $assignedSite);

        $transfer = InventoryDocument::query()->create([
            'document_number' => 'TRF-ACCESS-001',
            'document_type' => 'transfer',
            'source_site_id' => $assignedSite->id,
            'destination_site_id' => $unassignedSite->id,
            'document_date' => now(),
            'status' => 'completed',
            'created_by' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/transfers')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->getJson("/api/transfers/{$transfer->id}")->assertForbidden();
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

    private function site(string $name, string $code, bool $isActive = true): Site
    {
        return Site::query()->create([
            'name' => $name,
            'code' => $code,
            'type' => 'shop',
            'is_active' => $isActive,
        ]);
    }

    private function product(): Product
    {
        $type = ProductType::query()->create([
            'name' => 'Test Part '.str()->random(5),
            'code' => strtoupper(str()->random(5)),
            'is_active' => true,
        ]);

        return Product::query()->create([
            'product_code' => 'PART-'.str()->upper(str()->random(8)),
            'product_name' => 'Test Product',
            'product_type_id' => $type->id,
            'default_selling_price' => 100,
            'default_purchase_price' => 50,
            'is_active' => true,
        ]);
    }

    private function assign(User $user, Site $site, array $flags = []): UserSiteAccess
    {
        return UserSiteAccess::query()->create([
            'user_id' => $user->id,
            'site_id' => $site->id,
            'access_level' => 'view_only',
            'is_active' => true,
            ...$flags,
        ]);
    }
}
