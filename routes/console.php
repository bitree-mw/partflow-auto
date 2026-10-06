<?php

use App\Services\MailDiagnosticsService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Lets Settings show whether the hosting cron is actually running.
Schedule::call(fn () => app(MailDiagnosticsService::class)->recordSchedulerHeartbeat())
    ->everyMinute()->name('scheduler-heartbeat');

Schedule::command('reminders:stock')->weeklyOn(1, '08:00')
    ->timezone(config('app.timezone', 'Africa/Blantyre'))->withoutOverlapping();

if (config('hosting.cron_queue')) {
    Schedule::command('queue:work', [
        'database',
        '--stop-when-empty',
        '--max-time=45',
        '--max-jobs=50',
        '--sleep=1',
        '--tries=3',
        '--timeout=60',
        '--backoff=60',
    ])->everyMinute()->withoutOverlapping(5)
        ->appendOutputTo(storage_path('logs/cron-queue.log'));
}
