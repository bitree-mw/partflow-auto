<?php

namespace App\Services;

use App\Mail\TestEmailMail;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class MailDiagnosticsService
{
    public const SCHEDULER_HEARTBEAT_KEY = 'scheduler:last_ran_at';

    private const STALE_AFTER_MINUTES = 5;

    private const NON_DELIVERING_MAILERS = ['log', 'array'];

    public function __construct(private readonly SystemConfigurationService $systemConfiguration) {}

    /**
     * Send a test message immediately (not queued) so transport errors are reported to the caller.
     *
     * @return array{sent: bool, message: string}
     */
    public function sendTestEmail(string $recipient, User $requestedBy): array
    {
        $mailer = (string) config('mail.default');

        if (in_array($mailer, self::NON_DELIVERING_MAILERS, true)) {
            return [
                'sent' => false,
                'message' => "Mail is set to \"{$mailer}\", so emails are not actually delivered. Set MAIL_MAILER=smtp and the SMTP settings in the server's .env file, then try again.",
            ];
        }

        try {
            Mail::to($recipient)->send(new TestEmailMail([
                'business_name' => $this->systemConfiguration->settings()['business_name'],
                'requested_by' => $requestedBy->name ?? $requestedBy->email,
                'sent_at' => now()->format('d M Y H:i'),
                'settings_url' => route('web.settings.index'),
            ]));
        } catch (Throwable $exception) {
            Log::warning('Test email delivery failed.', [
                'user_id' => $requestedBy->getKey(),
                'mailer' => $mailer,
                'exception_type' => class_basename($exception),
            ]);

            return [
                'sent' => false,
                'message' => $this->failureMessage($exception),
            ];
        }

        return [
            'sent' => true,
            'message' => "Test email accepted by the mail server for {$recipient}. If it does not arrive within a few minutes, check the spam folder.",
        ];
    }

    /**
     * Read-only delivery health shown on the settings page.
     *
     * @return list<array{label: string, tone: string, status: string, detail: string}>
     */
    public function deliveryStatus(): array
    {
        return [
            $this->mailerCheck(),
            $this->schedulerCheck(),
            $this->queueCheck(),
        ];
    }

    public function recordSchedulerHeartbeat(): void
    {
        Cache::forever(self::SCHEDULER_HEARTBEAT_KEY, now()->toIso8601String());
    }

    private function mailerCheck(): array
    {
        $mailer = (string) config('mail.default');

        if (in_array($mailer, self::NON_DELIVERING_MAILERS, true)) {
            return $this->check('Mail sending', 'danger', 'Not delivering', "MAIL_MAILER is \"{$mailer}\"; emails are not sent. Use smtp in production.");
        }

        $host = config("mail.mailers.{$mailer}.host");
        $port = config("mail.mailers.{$mailer}.port");
        $from = config('mail.from.address');
        $target = filled($host) ? " via {$host}:{$port}" : '';

        return $this->check('Mail sending', 'success', Str::upper($mailer), "Sends{$target} from {$from}.");
    }

    private function schedulerCheck(): array
    {
        try {
            $lastRan = Cache::get(self::SCHEDULER_HEARTBEAT_KEY);
        } catch (Throwable) {
            $lastRan = null;
        }

        if (! is_string($lastRan) || $lastRan === '') {
            return $this->check('Cron (scheduled tasks)', 'warning', 'Not seen', 'No cron run has been recorded. Reminders and queued alerts will not send until the every-minute cron job runs.');
        }

        $ranAt = Carbon::parse($lastRan);

        if ($ranAt->lt(now()->subMinutes(self::STALE_AFTER_MINUTES))) {
            return $this->check('Cron (scheduled tasks)', 'danger', 'Stopped', "Last ran {$ranAt->diffForHumans()}. Check the cron job in the hosting control panel.");
        }

        return $this->check('Cron (scheduled tasks)', 'success', 'Running', "Last ran {$ranAt->diffForHumans()}.");
    }

    private function queueCheck(): array
    {
        $connection = (string) config('queue.default');

        if ($connection !== 'database') {
            return $this->check('Background email queue', 'warning', Str::upper($connection), "QUEUE_CONNECTION is \"{$connection}\". Shared hosting should use database with CPANEL_CRON_QUEUE=true.");
        }

        if (! config('hosting.cron_queue')) {
            return $this->check('Background email queue', 'warning', 'No cron worker', 'CPANEL_CRON_QUEUE is off, so cron does not send queued emails. Set CPANEL_CRON_QUEUE=true unless a separate queue worker runs.');
        }

        try {
            $jobsTable = config('queue.connections.database.table', 'jobs');
            $failedTable = config('queue.failed.table', 'failed_jobs');

            $pending = Schema::hasTable($jobsTable) ? DB::table($jobsTable)->count() : 0;
            $oldestAvailableAt = $pending > 0 ? DB::table($jobsTable)->min('available_at') : null;
            $failed = Schema::hasTable($failedTable) ? DB::table($failedTable)->count() : 0;
        } catch (Throwable) {
            return $this->check('Background email queue', 'warning', 'Unknown', 'The queue tables could not be read.');
        }

        $summary = "{$pending} waiting, {$failed} failed.";

        if ($failed > 0) {
            return $this->check('Background email queue', 'danger', 'Failures', "{$summary} Check the Laravel log for the mail error.");
        }

        if ($oldestAvailableAt !== null && Carbon::createFromTimestamp((int) $oldestAvailableAt)->lt(now()->subMinutes(self::STALE_AFTER_MINUTES))) {
            $waitingSince = Carbon::createFromTimestamp((int) $oldestAvailableAt)->diffForHumans();

            return $this->check('Background email queue', 'warning', 'Backlog', "{$summary} Oldest has waited since {$waitingSince}; the cron worker may not be running.");
        }

        return $this->check('Background email queue', 'success', 'Clear', $summary);
    }

    private function failureMessage(Throwable $exception): string
    {
        $mailer = (string) config('mail.default');
        $host = config("mail.mailers.{$mailer}.host");
        $port = config("mail.mailers.{$mailer}.port");
        $raw = $exception->getMessage();
        $lower = Str::lower($raw);

        $hint = match (true) {
            Str::contains($lower, ['535', 'authenticat', 'credentials']) => 'The mail server rejected the username or password. Check MAIL_USERNAME (usually the full mailbox address) and MAIL_PASSWORD.',
            Str::contains($lower, ['certificate', 'ssl', 'tls', 'crypto']) => 'The secure connection failed. Use port 465 with MAIL_SCHEME=smtps, or port 587 with MAIL_SCHEME=smtp.',
            Str::contains($lower, ['could not be established', 'timed out', 'connection refused', 'getaddrinfo', 'network is unreachable']) => "The server could not reach the mail server at {$host}:{$port}. Check MAIL_HOST and MAIL_PORT, and ask the host whether outgoing SMTP is allowed.",
            Str::contains($lower, ['550', '553', '554', 'sender', 'relay']) => 'The mail server refused the message. MAIL_FROM_ADDRESS usually has to be a mailbox on the same domain as MAIL_USERNAME.',
            default => 'The mail server returned an error.',
        };

        return "Test email failed. {$hint} Details: {$this->sanitize($raw)}";
    }

    private function sanitize(string $message): string
    {
        $password = config('mail.mailers.smtp.password');

        if (is_string($password) && $password !== '') {
            $message = str_replace($password, '[redacted]', $message);
        }

        $message = preg_replace('/\b(password|token|secret)\s*[:=]\s*\S+/i', '$1=[redacted]', $message) ?? $message;

        return Str::limit(Str::squish($message), 400) ?: 'No error message was returned.';
    }

    private function check(string $label, string $tone, string $status, string $detail): array
    {
        return compact('label', 'tone', 'status', 'detail');
    }
}
