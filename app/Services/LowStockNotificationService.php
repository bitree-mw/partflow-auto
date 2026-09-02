<?php

namespace App\Services;

use App\Mail\LowStockAlertMail;
use App\Models\SiteStock;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\DB;
use Throwable;

class LowStockNotificationService
{
    public function __construct(
        private readonly SystemConfigurationService $systemConfiguration,
        private readonly Mailer $mailer
    ) {}

    public function handleQuantityChange(
        SiteStock $stock,
        int $availableBefore,
        int $availableAfter
    ): void {
        $stock->loadMissing(['product', 'site']);
        $threshold = $stock->effective_low_stock_level;

        if ($threshold <= 0 || $availableAfter > $threshold) {
            $this->resetNotificationCycle($stock);

            return;
        }

        if ($availableAfter >= $availableBefore || $stock->low_stock_notified_at !== null) {
            return;
        }

        $recipient = $this->systemConfiguration->lowStockNotificationEmail();

        if ($recipient === null) {
            return;
        }

        $notifiedAt = now();
        $stock->forceFill(['low_stock_notified_at' => $notifiedAt])->save();

        $alert = [
            'business_name' => $this->systemConfiguration->settings()['business_name'],
            'product_id' => $stock->product_id,
            'site_id' => $stock->site_id,
            'product_code' => $stock->product->product_code,
            'product_name' => $stock->product->product_name,
            'site_name' => $stock->site->name,
            'quantity_on_hand' => $stock->quantity_on_hand,
            'reserved_quantity' => $stock->reserved_quantity,
            'available_quantity' => $availableAfter,
            'low_stock_level' => $threshold,
            'alerts_url' => route('web.alerts.index'),
        ];

        DB::afterCommit(function () use ($recipient, $alert, $stock, $notifiedAt): void {
            $mail = new LowStockAlertMail(
                alert: $alert,
                siteStockId: $stock->getKey(),
                notificationCycleStartedAt: $notifiedAt->format('Y-m-d H:i:s')
            );

            try {
                $this->mailer->to($recipient)->queue($mail);
            } catch (Throwable $exception) {
                $mail->recordFailure($exception, 'queue_dispatch');
            }
        });
    }

    private function resetNotificationCycle(SiteStock $stock): void
    {
        if ($stock->low_stock_notified_at === null) {
            return;
        }

        $stock->forceFill(['low_stock_notified_at' => null])->save();
    }
}
