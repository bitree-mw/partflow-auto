<?php

namespace Tests\Feature;

use App\Mail\BusinessReminderMail;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CpanelCronQueueTest extends TestCase
{
    use RefreshDatabase;

    private function queueEvents(bool $enabled): Collection
    {
        config(['hosting.cron_queue' => $enabled]);
        $this->app->forgetInstance(Schedule::class);
        \Illuminate\Support\Facades\Schedule::clearResolvedInstance(Schedule::class);
        require base_path('routes/console.php');

        return collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'queue:work'));
    }

    public function test_cron_worker_is_opt_in_and_has_a_shared_overlap_lock(): void
    {
        $this->assertCount(0, $this->queueEvents(false));
        $event = $this->queueEvents(true)->sole();
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(5, $event->expiresAt);
        $this->assertStringContainsString('--stop-when-empty', $event->command);
        $this->assertStringContainsString('--max-time=45', $event->command);

        $this->assertTrue($event->mutex->create($event));
        try {
            $this->assertTrue($event->shouldSkipDueToOverlapping());
        } finally {
            $event->mutex->forget($event);
        }
    }

    public function test_short_lived_worker_delivers_database_queued_mail_and_exits(): void
    {
        config(['queue.default' => 'database', 'mail.default' => 'array']);
        Mail::to('cron@example.test')->queue(new BusinessReminderMail([
            'subject' => 'Cron test', 'business_name' => 'PartFlow',
            'heading' => 'Test', 'description' => 'Test', 'prepared_at' => 'Test',
            'count_label' => 'One', 'columns' => ['Part'], 'rows' => [['Test']],
            'review_url' => 'https://example.test', 'review_label' => 'Review',
        ]));

        $this->assertDatabaseCount('jobs', 1);
        $this->artisan('queue:work', [
            'connection' => 'database', '--stop-when-empty' => true,
            '--max-time' => 45, '--max-jobs' => 50, '--sleep' => 1,
            '--tries' => 3, '--timeout' => 60, '--backoff' => 60,
        ])->assertSuccessful();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertCount(1, Mail::mailer()->getSymfonyTransport()->messages());
    }
}
