<?php

namespace Tests\Feature;

use App\Mail\LowStockAlertMail;
use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\User;
use App\Services\StockMovementService;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class LowStockEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_set_the_low_stock_email_under_business_information(): void
    {
        $this->actingAs($this->adminUser());

        $this->get(route('web.settings.index'))
            ->assertOk()
            ->assertSee('Low-stock notification email')
            ->assertSee(route('web.settings.business-information.update'), false);

        $this->post(route('web.settings.business-information.update'), $this->businessSettings([
            'low_stock_notification_email' => 'inventory@example.test',
        ]))->assertRedirect(route('web.settings.index').'#company-profile');

        $this->assertSame(
            'inventory@example.test',
            BusinessSetting::query()->where('key', 'low_stock_notification_email')->firstOrFail()->value
        );
    }

    public function test_business_information_rejects_an_invalid_notification_email(): void
    {
        $this->actingAs($this->adminUser());

        $this->from(route('web.settings.index'))
            ->post(route('web.settings.business-information.update'), $this->businessSettings([
                'low_stock_notification_email' => 'not-an-email',
            ]))
            ->assertRedirect(route('web.settings.index'))
            ->assertSessionHasErrors('low_stock_notification_email');

        $this->assertDatabaseMissing('business_settings', [
            'key' => 'low_stock_notification_email',
        ]);
    }

    public function test_email_is_queued_once_per_low_stock_cycle_and_resets_after_replenishment(): void
    {
        Mail::fake();
        BusinessSetting::query()->create([
            'key' => 'low_stock_notification_email',
            'value' => 'stock@example.test',
        ]);

        [$user, $site, $product, $stock] = $this->inventory(quantity: 10, threshold: 5);
        $movements = app(StockMovementService::class);

        $movements->decrease($product->id, $site->id, 5, 'sale_out', $user->id);

        Mail::assertQueued(LowStockAlertMail::class, function (LowStockAlertMail $mail): bool {
            $mail->assertSeeInHtml('Review stock alerts');

            return $mail->hasTo('stock@example.test')
                && $mail->alert['product_code'] === 'EMAIL-001'
                && $mail->alert['available_quantity'] === 5
                && $mail->alert['low_stock_level'] === 5;
        });
        $this->assertNotNull($stock->fresh()->low_stock_notified_at);

        $movements->decrease($product->id, $site->id, 1, 'sale_out', $user->id);
        Mail::assertQueued(LowStockAlertMail::class, 1);

        $movements->increase($product->id, $site->id, 10, 'purchase_in', $user->id);
        $this->assertNull($stock->fresh()->low_stock_notified_at);

        $movements->decrease($product->id, $site->id, 9, 'sale_out', $user->id);
        Mail::assertQueued(LowStockAlertMail::class, 2);
    }

    public function test_zero_threshold_or_missing_recipient_disables_low_stock_email(): void
    {
        Mail::fake();
        BusinessSetting::query()->create([
            'key' => 'low_stock_notification_email',
            'value' => 'stock@example.test',
        ]);

        [$user, $site, $product] = $this->inventory(quantity: 2, threshold: 0);
        app(StockMovementService::class)->decrease($product->id, $site->id, 2, 'sale_out', $user->id);

        Mail::assertNothingQueued();

        BusinessSetting::query()->where('key', 'low_stock_notification_email')->delete();
        [$secondUser, $secondSite, $secondProduct] = $this->inventory(quantity: 2, threshold: 1, suffix: '2');
        app(StockMovementService::class)->decrease($secondProduct->id, $secondSite->id, 1, 'sale_out', $secondUser->id);

        Mail::assertNothingQueued();
    }

    public function test_mail_failure_does_not_roll_back_stock_and_allows_a_later_retry(): void
    {
        BusinessSetting::query()->create([
            'key' => 'low_stock_notification_email',
            'value' => 'stock@example.test',
        ]);
        [$user, $site, $product, $stock] = $this->inventory(quantity: 6, threshold: 5);

        $pendingMail = \Mockery::mock(PendingMail::class);
        $pendingMail->shouldReceive('queue')
            ->once()
            ->with(\Mockery::type(LowStockAlertMail::class))
            ->andThrow(new RuntimeException('Test queue dispatch failure'));

        $mailer = \Mockery::mock(Mailer::class);
        $mailer->shouldReceive('to')
            ->once()
            ->with('stock@example.test')
            ->andReturn($pendingMail);
        $this->app->instance(Mailer::class, $mailer);

        app(StockMovementService::class)->decrease($product->id, $site->id, 1, 'sale_out', $user->id);

        $this->assertSame(5, $stock->fresh()->quantity_on_hand);
        $this->assertNull($stock->fresh()->low_stock_notified_at);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'site_id' => $site->id,
            'quantity_change' => -1,
        ]);
    }

    public function test_queued_mail_retries_and_releases_the_notification_cycle_after_final_failure(): void
    {
        [, , $product, $stock] = $this->inventory(quantity: 5, threshold: 5);
        $cycleStartedAt = now()->startOfSecond();
        $stock->forceFill(['low_stock_notified_at' => $cycleStartedAt])->save();

        $mail = new LowStockAlertMail(
            alert: [
                'product_id' => $product->id,
                'site_id' => $stock->site_id,
            ],
            siteStockId: $stock->id,
            notificationCycleStartedAt: $cycleStartedAt->format('Y-m-d H:i:s')
        );

        $this->assertInstanceOf(ShouldQueue::class, $mail);
        $this->assertSame(3, $mail->tries);
        $this->assertSame([60, 300], $mail->backoff);

        $mail->failed(new RuntimeException('Test final transport failure'));

        $this->assertNull($stock->fresh()->low_stock_notified_at);
    }

    private function inventory(int $quantity, int $threshold, string $suffix = '1'): array
    {
        $user = User::factory()->create();
        $site = Site::query()->create([
            'name' => "Email Test Branch {$suffix}",
            'code' => "EM{$suffix}",
            'type' => 'branch',
            'is_active' => true,
        ]);
        $productType = ProductType::query()->create([
            'name' => "Email Test Part {$suffix}",
            'code' => "ET{$suffix}",
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'product_code' => $suffix === '1' ? 'EMAIL-001' : "EMAIL-00{$suffix}",
            'product_name' => "Email Test Product {$suffix}",
            'product_type_id' => $productType->id,
            'default_low_stock_level' => $threshold,
            'is_active' => true,
        ]);
        $stock = SiteStock::query()->create([
            'product_id' => $product->id,
            'site_id' => $site->id,
            'quantity_on_hand' => $quantity,
            'reserved_quantity' => 0,
            'low_stock_level' => $threshold,
        ]);

        return [$user, $site, $product, $stock];
    }

    private function businessSettings(array $overrides = []): array
    {
        return array_merge([
            'settings_panel' => 'company-profile',
            'business_name' => 'PartFlow Auto',
            'legal_name' => 'PartFlow Auto Limited',
            'registration_number' => 'MW-TEST-001',
            'base_country' => 'Malawi',
            'base_currency' => 'MWK',
            'low_stock_notification_email' => null,
            'default_branch' => 'All sites',
            'stock_costing_method' => 'Last purchase cost',
            'low_stock_policy' => 'Use product default unless branch override exists',
        ], $overrides);
    }

    private function adminUser(): User
    {
        $role = Role::query()->create([
            'name' => 'Low-stock Test Administrator',
            'permissions' => ['*'],
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
