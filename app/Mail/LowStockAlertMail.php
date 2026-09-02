<?php

namespace App\Mail;

use App\Models\SiteStock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class LowStockAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public array $backoff = [60, 300];

    public function __construct(
        public readonly array $alert,
        public readonly int $siteStockId,
        public readonly string $notificationCycleStartedAt
    ) {}

    public function envelope(): Envelope
    {
        $status = $this->alert['available_quantity'] === 0 ? 'Out of stock' : 'Low stock';

        return new Envelope(
            subject: "{$status}: {$this->alert['product_code']} at {$this->alert['site_name']}"
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.low-stock-alert');
    }

    public function failed(Throwable $exception): void
    {
        $this->recordFailure($exception, 'delivery');
    }

    public function recordFailure(Throwable $exception, string $stage): void
    {
        try {
            SiteStock::query()
                ->whereKey($this->siteStockId)
                ->where('low_stock_notified_at', $this->notificationCycleStartedAt)
                ->update(['low_stock_notified_at' => null]);

            Log::warning('Low-stock email delivery failed.', [
                'site_stock_id' => $this->siteStockId,
                'product_id' => $this->alert['product_id'] ?? null,
                'site_id' => $this->alert['site_id'] ?? null,
                'stage' => $stage,
                'exception_type' => class_basename($exception),
                'error' => $this->sanitizedErrorMessage($exception),
            ]);
        } catch (Throwable) {
            // Mail failure reporting must never affect an already committed stock operation.
        }
    }

    private function sanitizedErrorMessage(Throwable $exception): string
    {
        $message = preg_replace(
            [
                '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
                '/\b(password|token|secret)\s*[:=]\s*\S+/i',
            ],
            ['[redacted-email]', '$1=[redacted]'],
            $exception->getMessage()
        ) ?? 'Mail transport failed without an error message.';

        return Str::limit(Str::squish($message), 500);
    }
}
