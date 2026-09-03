<?php

namespace App\Services;

use App\Jobs\SendScheduledEmailReminder;
use App\Mail\BusinessReminderMail;
use App\Models\ScheduledEmailReminder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class ScheduledReminderService
{
    public const WEEKLY_STOCK = 'weekly_stock';

    public function __construct(private readonly SystemConfigurationService $configuration) {}

    public function queueWeeklyStock(): bool
    {
        if (! now()->isMonday() || $this->configuration->lowStockNotificationEmail() === null) {
            return false;
        }

        if ($this->lowStockRows()->isEmpty()) {
            return false;
        }

        return $this->queue(self::WEEKLY_STOCK, now()->toDateString());
    }

    public function lowStockRows(): Collection
    {
        return DB::table('site_stocks')
            ->join('products', 'products.id', '=', 'site_stocks.product_id')
            ->join('sites', 'sites.id', '=', 'site_stocks.site_id')
            ->whereNull('products.deleted_at')
            ->whereNull('sites.deleted_at')
            ->where('products.is_active', true)
            ->where('sites.is_active', true)
            ->whereRaw('COALESCE(site_stocks.low_stock_level, products.default_low_stock_level, 0) > 0')
            ->whereRaw('(site_stocks.quantity_on_hand - site_stocks.reserved_quantity) <= COALESCE(site_stocks.low_stock_level, products.default_low_stock_level, 0)')
            ->select('products.product_code', 'products.product_name', 'sites.name as site_name', 'site_stocks.quantity_on_hand', 'site_stocks.reserved_quantity')
            ->selectRaw('(site_stocks.quantity_on_hand - site_stocks.reserved_quantity) as available_quantity')
            ->selectRaw('COALESCE(site_stocks.low_stock_level, products.default_low_stock_level, 0) as threshold')
            ->orderBy('sites.name')->orderBy('products.product_code')
            ->get();
    }

    private function queue(string $type, string $periodStart): bool
    {
        if (in_array(config('queue.connections.'.config('queue.default').'.driver'), ['sync', 'null', 'deferred'], true)) {
            throw new RuntimeException('Scheduled reminders require a persistent background queue connection, such as database.');
        }

        $id = DB::transaction(function () use ($type, $periodStart): ?int {
            $record = ScheduledEmailReminder::query()->firstOrCreate([
                'type' => $type,
                'period_start' => $periodStart,
            ]);
            $created = $record->wasRecentlyCreated;
            $record = ScheduledEmailReminder::query()->lockForUpdate()->findOrFail($record->id);

            if (! $created && $record->status !== 'failed') {
                return null;
            }

            $record->update(['status' => 'queued', 'failure_type' => null]);

            return $record->id;
        });

        if ($id === null) {
            return false;
        }

        try {
            Bus::dispatch(new SendScheduledEmailReminder($id));
        } catch (Throwable $exception) {
            $this->recordFailure($id, $exception);
            throw $exception;
        }

        return true;
    }

    public function send(int $id): void
    {
        $record = ScheduledEmailReminder::query()->findOrFail($id);
        if (in_array($record->status, ['sent', 'skipped'], true)) {
            return;
        }

        if ($record->type !== self::WEEKLY_STOCK) {
            throw new RuntimeException('Unsupported scheduled reminder type.');
        }

        $recipient = $this->configuration->lowStockNotificationEmail();
        if ($recipient === null || CarbonImmutable::parse($record->period_start)->lt(now()->startOfWeek())) {
            $record->update(['status' => 'skipped']);

            return;
        }

        $rows = $this->lowStockRows();
        if ($rows->isEmpty()) {
            $record->update(['status' => 'skipped']);

            return;
        }

        $settings = $this->configuration->settings();
        $data = [
            'business_name' => $settings['business_name'],
            'subject' => $settings['business_name'].' — Monday low-stock reminder',
            'heading' => 'Weekly low-stock reminder',
            'description' => 'These active parts remain at or below their branch reorder levels, including parts with no available stock.',
            'prepared_at' => now()->format('d M Y H:i').' '.config('app.timezone'),
            'count_label' => $rows->count().' branch/part stock records need attention.',
            'columns' => ['Part code', 'Part', 'Branch', 'On hand', 'Reserved', 'Available', 'Low-stock level'],
            'rows' => $rows->map(fn ($row) => [
                $row->product_code, $row->product_name, $row->site_name,
                number_format($row->quantity_on_hand), number_format($row->reserved_quantity),
                number_format(max(0, $row->available_quantity)), number_format($row->threshold),
            ])->all(),
            'review_url' => route('web.alerts.index'),
            'review_label' => 'Review stock alerts',
        ];

        Mail::to($recipient)->send(new BusinessReminderMail($data));
        $record->update(['status' => 'sent', 'sent_at' => now(), 'item_count' => $rows->count(), 'failure_type' => null]);
        Log::info('Scheduled reminder accepted by mail transport.', ['reminder_id' => $id, 'type' => $record->type, 'item_count' => $rows->count()]);
    }

    public function recordFailure(int $id, ?Throwable $exception): void
    {
        ScheduledEmailReminder::query()->whereKey($id)->whereNull('sent_at')->update([
            'status' => 'failed',
            'failure_type' => $exception ? class_basename($exception) : 'Unknown',
        ]);
        Log::warning('Scheduled reminder failed.', ['reminder_id' => $id, 'exception_type' => $exception ? class_basename($exception) : 'Unknown']);
    }
}
