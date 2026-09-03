<?php

namespace App\Console\Commands;

use App\Services\ScheduledReminderService;
use Illuminate\Console\Command;

class QueueStockReminders extends Command
{
    protected $signature = 'reminders:stock {--dry-run : Inspect eligibility without queuing or sending email}';

    protected $description = 'Queue the Monday low-stock digest for the configured business recipient';

    public function handle(ScheduledReminderService $reminders): int
    {
        if ($this->option('dry-run')) {
            $this->info($reminders->lowStockRows()->count().' low-stock records; '.(now()->isMonday() ? 'Monday' : 'not Monday').'. No email queued.');

            return self::SUCCESS;
        }

        $this->info($reminders->queueWeeklyStock() ? 'Weekly stock reminder queued.' : 'No weekly stock reminder due.');

        return self::SUCCESS;
    }
}
