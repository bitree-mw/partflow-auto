<?php

namespace App\Services;

use App\Repositories\DashboardRepository;
use App\Models\InventoryDocument;
use App\Models\Site;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        private readonly DashboardRepository $dashboard,
        private readonly SystemConfigurationService $systemConfiguration
    ) {}

    public function summary(array $filters = []): array
    {
        $summary = $this->baseSummary($filters);

        $summary['overview'] = $this->overviewFromSummary($summary, $filters);

        return $summary;
    }

    public function overview(array $filters = []): array
    {
        return $this->overviewFromSummary($this->baseSummary($filters), $filters);
    }

    private function baseSummary(array $filters = []): array
    {
        $siteId = $this->siteId($filters);

        return [
            'today_sales' => $this->dashboard->todaySales($siteId),
            'today_profit' => $this->dashboard->todayProfit($siteId),
            'total_stock_value' => $this->dashboard->totalStockValue($siteId),
            'low_stock_count' => $this->dashboard->lowStockCount($siteId),
            'out_of_stock_count' => $this->dashboard->outOfStockCount($siteId),
            'outstanding_customer_balances' => $this->dashboard->outstandingCustomerBalances($siteId),
            'recent_sales' => $this->dashboard->recentDocuments('sale', 5, $siteId),
            'recent_purchases' => $this->dashboard->recentDocuments('purchase', 5, $siteId),
            'recent_transfers' => $this->dashboard->recentDocuments('transfer', 5, $siteId),
            'recent_stock_movements' => $this->dashboard->recentStockMovements(10, $siteId),
        ];
    }

    private function overviewFromSummary(array $summary, array $filters = []): array
    {
        $currency = $this->systemConfiguration->settings()['base_currency'];
        $siteId = $this->siteId($filters);
        $todaySaleCount = $this->dashboard->todaySaleCount($siteId);
        $topParts = $this->topParts($currency, $siteId);
        $salesTrend = $this->salesTrend($currency, $siteId);
        $branchPerformance = $this->branchPerformance($currency, $siteId);
        $stockAlerts = $this->stockAlerts($siteId);
        $selectedSite = $siteId ? Site::query()->find($siteId) : null;

        return [
            'branchOptions' => $this->branchOptions(),
            'selectedBranchId' => $siteId,
            'selectedBranchName' => $selectedSite?->name ?? 'All branches',
            'metrics' => [
                [
                    'label' => 'Today sales',
                    'value' => $this->formatCurrency((float) $summary['today_sales'], $currency, true),
                    'change' => "{$todaySaleCount} sales today",
                    'tone' => 'good',
                    'trend' => 'positive',
                ],
                [
                    'label' => 'Today profit',
                    'value' => $this->formatCurrency((float) $summary['today_profit'], $currency, true),
                    'change' => $this->marginLabel((float) $summary['today_profit'], (float) $summary['today_sales']),
                    'tone' => 'good',
                    'trend' => 'positive',
                ],
                [
                    'label' => 'Stock value',
                    'value' => $this->formatCurrency((float) $summary['total_stock_value'], $currency, true),
                    'change' => $branchPerformance->count().' active sites',
                    'tone' => 'neutral',
                    'trend' => 'neutral',
                ],
                [
                    'label' => 'Loss exposure',
                    'value' => $this->formatCurrency($this->lossExposure($siteId), $currency, true),
                    'change' => $summary['low_stock_count'].' low stock warnings',
                    'tone' => 'risk',
                    'trend' => 'negative',
                ],
            ],
            'currentSales' => $this->currentSales($summary['recent_sales'], $currency),
            'mostSoldParts' => $topParts,
            'salesTrend' => $salesTrend,
            'branchPerformance' => $branchPerformance,
            'lossRisks' => $this->lossRisks($currency, $siteId),
            'stockAlerts' => $stockAlerts,
            'averageSale' => $this->formatCurrency(
                $todaySaleCount > 0 ? (float) $summary['today_sales'] / $todaySaleCount : 0,
                $currency,
                true
            ),
        ];
    }

    private function currentSales(Collection $sales, string $currency): Collection
    {
        return $sales
            ->take(5)
            ->map(fn (InventoryDocument $sale) => [
                'invoice' => $sale->document_number,
                'branch' => $sale->sourceSite?->name ?? 'Unassigned',
                'customer' => $sale->contact?->name ?? 'Walk-in',
                'amount' => $this->formatCurrency((float) $sale->total_amount, $currency),
                'profit' => $this->formatCurrency((float) $sale->items->sum('profit_amount'), $currency),
                'status' => str($sale->payment_status)->headline()->toString(),
                'payment_tone' => match ($sale->payment_status) {
                    'paid' => 'success',
                    'partial' => 'warning',
                    default => 'danger',
                },
            ])
            ->values();
    }

    private function topParts(string $currency, ?int $siteId = null): Collection
    {
        $rows = $this->dashboard->topSellingProducts([
            'date_from' => today()->subDays(29)->toDateString(),
            'date_to' => today()->toDateString(),
            ...($siteId ? ['site_id' => $siteId] : []),
        ], 5);
        $maxUnits = max((int) $rows->max('quantity_sold'), 1);

        return $rows
            ->map(fn ($row) => [
                'part' => $row->product_name,
                'code' => $row->product_code,
                'units' => (int) $row->quantity_sold,
                'sales' => $this->formatCurrency((float) $row->sales_amount, $currency, true),
                'profit' => $this->formatCurrency((float) $row->profit_amount, $currency, true),
                'share' => (int) round(((int) $row->quantity_sold / $maxUnits) * 100),
            ])
            ->values();
    }

    private function salesTrend(string $currency, ?int $siteId = null): Collection
    {
        $rows = $this->dashboard->salesTrend(siteId: $siteId);
        $maxSales = max((float) $rows->max('sales_amount'), 1);

        return $rows
            ->map(fn (array $row) => [
                'label' => Carbon::parse($row['date'])->format('D'),
                'value' => $this->formatCurrency((float) $row['sales_amount'], $currency, true),
                'height' => max(8, (int) round(((float) $row['sales_amount'] / $maxSales) * 100)),
            ])
            ->values();
    }

    private function branchPerformance(string $currency, ?int $siteId = null): Collection
    {
        $rows = $this->dashboard->branchPerformance([
            'date_from' => today()->toDateString(),
            'date_to' => today()->toDateString(),
            ...($siteId ? ['site_id' => $siteId] : []),
        ]);
        $maxSales = max((float) $rows->max('sales_amount'), 1);
        $maxProfit = max((float) $rows->max('profit_amount'), 1);

        return $rows->map(function (array $branch) use ($currency, $siteId, $maxSales, $maxProfit) {
            $margin = $this->marginPercent($branch['profit_amount'], $branch['sales_amount']);

            return [
                'site_id' => $branch['site_id'],
                'branch' => $branch['site_name'],
                'sales' => $this->formatCurrency($branch['sales_amount'], $currency, true),
                'profit' => $this->formatCurrency($branch['profit_amount'], $currency, true),
                'sales_raw' => $branch['sales_amount'],
                'profit_raw' => $branch['profit_amount'],
                'sales_width' => max(4, (int) round(($branch['sales_amount'] / $maxSales) * 100)),
                'profit_width' => max(4, (int) round(($branch['profit_amount'] / $maxProfit) * 100)),
                'selected' => $siteId !== null && (int) $branch['site_id'] === $siteId,
                'margin' => number_format($margin, 1).'%',
                'margin_tone' => $margin < 0 ? 'negative' : 'positive',
                'stockouts' => $branch['stockout_count'],
            ];
        })->values();
    }

    private function stockAlerts(?int $siteId = null): Collection
    {
        return $this->dashboard->lowStockAlerts(filters: $siteId ? ['site_id' => $siteId] : [])
            ->map(fn ($row) => [
                'part' => $row->product_name,
                'branch' => $row->site_name,
                'available' => (int) $row->available_quantity,
                'recommended' => (int) $row->low_stock_level,
                'priority_tone' => ((int) $row->available_quantity <= 0) ? 'danger' : 'warning',
            ])
            ->values();
    }

    private function lossRisks(string $currency, ?int $siteId = null): Collection
    {
        return collect([
            [
                'label' => 'Purchase returns',
                'value' => $this->formatCurrency($this->dashboard->pendingPurchaseReturnTotal($siteId), $currency, true),
                'detail' => 'Open supplier return documents awaiting completion',
            ],
            [
                'label' => 'Stock take variance',
                'value' => $this->formatCurrency($this->dashboard->stockTakeVarianceTotal($siteId), $currency, true),
                'detail' => 'Recorded variance value from stock counts',
            ],
            [
                'label' => 'Customer balances',
                'value' => $this->formatCurrency($this->dashboard->outstandingCustomerBalances($siteId), $currency, true),
                'detail' => 'Unpaid or partially paid sales balances',
            ],
        ]);
    }

    private function lossExposure(?int $siteId = null): float
    {
        return $this->dashboard->pendingPurchaseReturnTotal($siteId)
            + $this->dashboard->stockTakeVarianceTotal($siteId)
            + $this->dashboard->outstandingCustomerBalances($siteId);
    }

    private function branchOptions(): Collection
    {
        return Site::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Site $site): array => [
                'id' => $site->id,
                'name' => $site->name,
            ])
            ->values();
    }

    private function siteId(array $filters): ?int
    {
        return filled($filters['site_id'] ?? null) ? (int) $filters['site_id'] : null;
    }

    private function formatCurrency(float $value, string $currency, bool $compact = false): string
    {
        $absolute = abs($value);
        $sign = $value < 0 ? '-' : '';

        if ($compact && $absolute >= 1000000) {
            return "{$currency} {$sign}".number_format($absolute / 1000000, 2).'M';
        }

        if ($compact && $absolute >= 1000) {
            return "{$currency} {$sign}".number_format($absolute / 1000, 0).'K';
        }

        return "{$currency} {$sign}".number_format($absolute, 0);
    }

    private function marginLabel(float $profit, float $sales): string
    {
        return number_format($this->marginPercent($profit, $sales), 1).'% margin';
    }

    private function marginPercent(float $profit, float $sales): float
    {
        return $sales > 0 ? ($profit / $sales) * 100 : 0;
    }
}
