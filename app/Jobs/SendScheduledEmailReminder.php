<?php

namespace App\Jobs;

use App\Services\ScheduledReminderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class SendScheduledEmailReminder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [60, 300];

    public function __construct(public readonly int $reminderId)
    {
        $this->afterCommit();
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('scheduled-email-reminders'))->releaseAfter(60)->expireAfter(120)];
    }

    public function handle(ScheduledReminderService $reminders): void
    {
        $reminders->send($this->reminderId);
    }

    public function failed(?Throwable $exception): void
    {
        app(ScheduledReminderService::class)->recordFailure($this->reminderId, $exception);
    }
}
