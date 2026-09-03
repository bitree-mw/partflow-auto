<?php

namespace Tests\Feature;

use App\Jobs\SendScheduledEmailReminder;
use App\Mail\BusinessReminderMail;
use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\ScheduledEmailReminder;
use App\Models\Site;
use App\Models\SiteStock;
use App\Services\ScheduledReminderService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class ScheduledRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 7)->setTime(8, 0));
        config(['queue.default' => 'database']);
        Queue::fake();
        Mail::fake();
        BusinessSetting::query()->create(['key' => 'low_stock_notification_email', 'value' => 'business@example.test']);
    }

    public function test_monday_digest_is_queued_once_per_week_even_when_cycle_email_was_already_marked(): void
    {
        $stock = $this->stock();
        $stock->update(['low_stock_notified_at' => now()->subDays(5)]);
        $service = app(ScheduledReminderService::class);

        $this->assertTrue($service->queueWeeklyStock());
        $this->assertFalse($service->queueWeeklyStock());
        Queue::assertPushed(SendScheduledEmailReminder::class, 1);
        Mail::assertNothingSent();

        $service->send(ScheduledEmailReminder::query()->sole()->id);
        Mail::assertSent(BusinessReminderMail::class, fn ($mail) => $mail->hasTo('business@example.test') && count($mail->reminder['rows']) === 1);
        $this->assertDatabaseHas('scheduled_email_reminders', ['status' => 'sent', 'item_count' => 1]);
        $this->assertNotNull($stock->fresh()->low_stock_notified_at);

        $service->send(ScheduledEmailReminder::query()->sole()->id);
        Mail::assertSentCount(1);

        $this->travel(7)->days();
        $this->assertTrue($service->queueWeeklyStock());
        Queue::assertPushed(SendScheduledEmailReminder::class, 2);
    }

    public function test_no_stock_mail_on_other_days_or_without_a_recipient(): void
    {
        $this->stock();
        $this->travel(1)->days();
        $this->assertFalse(app(ScheduledReminderService::class)->queueWeeklyStock());
        $this->travelBack();
        $this->travelTo(now()->setDate(2026, 9, 7)->setTime(8, 0));
        BusinessSetting::query()->where('key', 'low_stock_notification_email')->delete();
        $this->assertFalse(app(ScheduledReminderService::class)->queueWeeklyStock());
        Queue::assertNothingPushed();
    }

    public function test_weekly_stock_rechecks_current_quantities_before_sending(): void
    {
        $stock = $this->stock();
        $service = app(ScheduledReminderService::class);
        $service->queueWeeklyStock();
        $stock->update(['quantity_on_hand' => 20]);
        $service->send(ScheduledEmailReminder::query()->sole()->id);

        Mail::assertNothingSent();
        $this->assertDatabaseHas('scheduled_email_reminders', ['status' => 'skipped', 'sent_at' => null]);
    }

    public function test_weekly_stock_uses_fallback_threshold_and_excludes_disabled_and_inactive_records(): void
    {
        $fallback = $this->stock('fallback');
        $fallback->update(['low_stock_level' => null]);
        $this->stock('disabled')->update(['low_stock_level' => 0]);
        $this->stock('inactive-product')->product->update(['is_active' => false]);
        $this->stock('inactive-site')->site->update(['is_active' => false]);
        $this->stock('deleted')->product->delete();
        $rows = app(ScheduledReminderService::class)->lowStockRows();

        $this->assertCount(1, $rows);
        $this->assertSame($fallback->product->product_code, $rows->first()->product_code);
    }

    public function test_weekly_stock_does_not_truncate_to_dashboard_alert_limit(): void
    {
        foreach (range(1, 30) as $number) {
            $this->stock((string) $number);
        }
        $service = app(ScheduledReminderService::class);
        $service->queueWeeklyStock();
        $service->send(ScheduledEmailReminder::query()->sole()->id);

        Mail::assertSent(BusinessReminderMail::class, function ($mail): bool {
            $mail->assertSeeInHtml('Weekly low-stock reminder');

            return count($mail->reminder['rows']) === 30;
        });
    }

    public function test_delivery_failure_is_recorded_without_marking_sent_and_can_be_requeued(): void
    {
        $this->stock();
        $service = app(ScheduledReminderService::class);
        $service->queueWeeklyStock();
        $id = ScheduledEmailReminder::query()->sole()->id;
        $job = new SendScheduledEmailReminder($id);
        $job->failed(new RuntimeException('Synthetic failure'));

        $this->assertDatabaseHas('scheduled_email_reminders', ['id' => $id, 'status' => 'failed', 'sent_at' => null, 'failure_type' => 'RuntimeException']);
        $this->assertTrue($service->queueWeeklyStock());
        Queue::assertPushed(SendScheduledEmailReminder::class, 2);
    }

    public function test_monday_command_is_registered_at_eight_in_the_application_timezone(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'reminders:stock'));
        $this->assertNotNull($event);
        $this->assertSame('0 8 * * 1', $event->expression);
        $this->assertSame(config('app.timezone'), $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_dry_run_never_queues_or_changes_notification_state(): void
    {
        $stock = $this->stock();
        $this->artisan('reminders:stock', ['--dry-run' => true])->assertSuccessful();
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('scheduled_email_reminders', 0);
        $this->assertNull($stock->fresh()->low_stock_notified_at);
    }

    public function test_sync_queue_is_rejected_instead_of_sending_mail_in_the_scheduler_process(): void
    {
        $this->stock();
        config(['queue.default' => 'sync']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('persistent background queue');
        try {
            app(ScheduledReminderService::class)->queueWeeklyStock();
        } finally {
            Mail::assertNothingSent();
            $this->assertDatabaseCount('scheduled_email_reminders', 0);
        }
    }

    public function test_dispatch_failure_leaves_a_traceable_retryable_record(): void
    {
        $this->stock();
        Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue unavailable'));
        $this->expectException(RuntimeException::class);
        try {
            app(ScheduledReminderService::class)->queueWeeklyStock();
        } finally {
            $this->assertDatabaseHas('scheduled_email_reminders', ['status' => 'failed', 'sent_at' => null, 'failure_type' => 'RuntimeException']);
            Mail::assertNothingSent();
        }
    }

    public function test_mail_exception_does_not_mark_the_reminder_sent_before_queue_retries(): void
    {
        $this->stock();
        $service = app(ScheduledReminderService::class);
        $service->queueWeeklyStock();
        $record = ScheduledEmailReminder::query()->sole();
        Mail::shouldReceive('to')->once()->with('business@example.test')->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP unavailable'));
        $this->expectException(RuntimeException::class);
        try {
            (new SendScheduledEmailReminder($record->id))->handle($service);
        } finally {
            $this->assertDatabaseHas('scheduled_email_reminders', ['id' => $record->id, 'status' => 'queued', 'sent_at' => null]);
        }
    }

    private function stock(string $suffix = '1'): SiteStock
    {
        $site = Site::query()->create(['name' => 'Reminder branch '.$suffix, 'code' => 'R'.$suffix, 'type' => 'branch', 'is_active' => true]);
        $type = ProductType::query()->firstOrCreate(['code' => 'REM'], ['name' => 'Reminder parts', 'is_active' => true]);
        $product = Product::query()->create(['product_code' => 'REM-'.$suffix, 'product_name' => 'Reminder part '.$suffix, 'product_type_id' => $type->id, 'default_low_stock_level' => 5, 'is_active' => true]);

        return SiteStock::query()->create(['product_id' => $product->id, 'site_id' => $site->id, 'quantity_on_hand' => 5, 'reserved_quantity' => 0, 'low_stock_level' => 5]);
    }
}
