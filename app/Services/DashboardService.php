<?php

namespace App\Services;

use App\Models\InventoryDocument;
use App\Models\Site;
use App\Models\User;
use App\Repositories\DashboardRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        private readonly DashboardRepository $dashboard,
        private readonly SystemConfigurationService $systemConfiguration,
        private readonly SiteAccessService $siteAccessService
    ) {}

    public function summary(array $filters = [], ?User $user = null): array
    {
        if ($user) {
            $filters = $this->siteAccessService->scopeFilters($user, $filters);
        }

        $summary = $this->baseSummary($filters);

        $summary['overview'] = $this->overviewFromSummary($summary, $filters, $user);

        return $summary;
    }

    public function overview(array $filters = [], ?User $user = null): array
    {
        if ($user) {
            $filters = $this->siteAccessService->scopeFilters($user, $filters);
        }

        return $this->overviewFromSummary($this->baseSummary($filters), $filters, $user);
    }

    public function todaySnapshot(array $filters = [], ?User $user = null): array
    {
        if ($user) {
            $filters = $this->siteAccessService->scopeFilters($user, $filters);
        }

        $siteId = $this->siteId($filters);
        $siteIds = $filters['site_ids'] ?? null;
        $currency = $this->systemConfiguration->settings()['base_currency'];
        $sales = $this->dashboard->todaySales($siteId, $siteIds);
        $profit = $this->dashboard->todayProfit($siteId, $siteIds);
        $saleCount = $this->dashboard->todaySaleCount($siteId, $siteIds);

        return [
            'today_sales' => $this->formatCurrency($sales, $currency, true),
            'today_sales_change' => $saleCount.' '.str('sale')->plural($saleCount).' completed today',
            'today_sale_count' => $saleCount,
            'today_profit' => $this->formatCurrency($profit, $currency, true),
            'today_profit_change' => $this->marginLabel($profit, $sales),
            'average_sale' => $this->formatCurrency($saleCount > 0 ? $sales / $saleCount : 0, $currency, true),
        ];
    }

    private function baseSummary(array $filters = []): array
    {
        $siteId = $this->siteId($filters);
        $siteIds = $filters['site_ids'] ?? null;

        return [
            'today_sales' => $this->dashboard->todaySales($siteId, $siteIds),
            'today_profit' => $this->dashboard->todayProfit($siteId, $siteIds),
            'total_stock_value' => $this->dashboard->totalStockValue($siteId, $siteIds),
            'low_stock_count' => $this->dashboard->lowStockCount($siteId, $siteIds),
            'out_of_stock_count' => $this->dashboard->outOfStockCount($siteId, $siteIds),
            'outstanding_customer_balances' => $this->dashboard->outstandingCustomerBalances($siteId, $siteIds),
            'outstanding_supplier_balances' => $this->dashboard->outstandingSupplierBalances($siteId, $siteIds),
            'recent_sales' => $this->dashboard->recentDocuments('sale', 5, $siteId, $siteIds),
            'recent_purchases' => $this->dashboard->recentDocuments('purchase', 5, $siteId, $siteIds),
            'recent_transfers' => $this->dashboard->recentDocuments('transfer', 5, $siteId, $siteIds),
            'recent_stock_movements' => $this->dashboard->recentStockMovements(10, $siteId, $siteIds),
        ];
    }

    private function overviewFromSummary(array $summary, array $filters = [], ?User $user = null): array
    {
        $currency = $this->systemConfiguration->settings()['base_currency'];
        $siteId = $this->siteId($filters);
        $siteIds = $filters['site_ids'] ?? null;
        $todaySaleCount = $this->dashboard->todaySaleCount($siteId, $siteIds);
        $topParts = $this->topParts($currency, $siteId, $siteIds);
        $revenuePeriod = (int) ($filters['revenue_period'] ?? 7);
        $salesTrend = $this->salesTrend($currency, $revenuePeriod, $siteId, $siteIds);
        $branchPerformance = $this->branchPerformance($currency, $siteId, $siteIds);
        $priorityActionSummary = $this->dashboard->priorityActionSummary($siteId, $siteIds);
        $selectedSite = $siteId ? Site::query()->find($siteId) : null;
        $currentMonthSales = $this->dashboard->salesTotalBetween(
            today()->startOfMonth()->toDateString(),
            today()->endOfMonth()->toDateString(),
            $siteId,
            $siteIds
        );
        $previousMonthSales = $this->dashboard->salesTotalBetween(
            today()->subMonthNoOverflow()->startOfMonth()->toDateString(),
            today()->subMonthNoOverflow()->toDateString(),
            $siteId,
            $siteIds
        );
        $salesComparison = $this->salesComparison($currentMonthSales, $previousMonthSales);
        $pendingOrderCount = $priorityActionSummary['pending_purchase_orders'];
        $activeSiteCount = $branchPerformance->count();
        $lowStockCount = (int) $summary['low_stock_count'];

        return [
            'branchOptions' => $this->branchOptions($siteIds),
            'selectedBranchId' => $siteId,
            'selectedBranchName' => $selectedSite?->name ?? 'All branches',
            'metrics' => [
                [
                    'label' => 'Inventory value',
                    'value' => $this->formatCurrency((float) $summary['total_stock_value'], $currency, true),
                    'change' => $activeSiteCount.' active '.str('site')->plural($activeSiteCount),
                    'tone' => 'neutral',
                    'trend' => 'positive',
                    'direction' => 'up',
                ],
                [
                    'label' => 'Low stock items',
                    'value' => number_format($lowStockCount),
                    'change' => $lowStockCount.' '.str('item')->plural($lowStockCount).' need attention',
                    'tone' => 'risk',
                    'trend' => $lowStockCount > 0 ? 'negative' : 'positive',
                    'direction' => $lowStockCount > 0 ? 'up' : 'flat',
                ],
                [
                    'label' => 'Sales this month',
                    'value' => $this->formatCurrency($currentMonthSales, $currency, true),
                    'change' => $salesComparison['label'],
                    'tone' => 'good',
                    'trend' => $salesComparison['trend'],
                    'direction' => $salesComparison['direction'],
                ],
                [
                    'label' => 'Pending orders',
                    'value' => number_format($pendingOrderCount),
                    'change' => $pendingOrderCount.' '.str('order')->plural($pendingOrderCount).' awaiting action',
                    'tone' => 'risk',
                    'trend' => $pendingOrderCount > 0 ? 'negative' : 'positive',
                    'direction' => $pendingOrderCount > 0 ? 'up' : 'flat',
                ],
                [
                    'label' => 'Today sales',
                    'value' => $this->formatCurrency((float) $summary['today_sales'], $currency, true),
                    'change' => $todaySaleCount.' '.str('sale')->plural($todaySaleCount).' completed today',
                    'tone' => 'good',
                    'trend' => $todaySaleCount > 0 ? 'positive' : 'neutral',
                    'direction' => $todaySaleCount > 0 ? 'up' : 'flat',
                ],
                [
                    'label' => 'Today profit',
                    'value' => $this->formatCurrency((float) $summary['today_profit'], $currency, true),
                    'change' => $this->marginLabel((float) $summary['today_profit'], (float) $summary['today_sales']),
                    'tone' => 'good',
                    'trend' => (float) $summary['today_profit'] > 0 ? 'positive' : ((float) $summary['today_profit'] < 0 ? 'negative' : 'neutral'),
                    'direction' => (float) $summary['today_profit'] > 0 ? 'up' : ((float) $summary['today_profit'] < 0 ? 'down' : 'flat'),
                ],
                [
                    'label' => 'Outstanding debt',
                    'value' => $this->formatCurrency((float) $summary['outstanding_customer_balances'], $currency, true),
                    'change' => 'Customer balances to collect',
                    'tone' => 'risk',
                    'trend' => (float) $summary['outstanding_customer_balances'] > 0 ? 'negative' : 'positive',
                    'direction' => (float) $summary['outstanding_customer_balances'] > 0 ? 'up' : 'flat',
                ],
                [
                    'label' => 'Out of stock',
                    'value' => number_format((int) $summary['out_of_stock_count']),
                    'change' => (int) $summary['out_of_stock_count'].' '.str('item')->plural((int) $summary['out_of_stock_count']).' unavailable for sale',
                    'tone' => 'risk',
                    'trend' => (int) $summary['out_of_stock_count'] > 0 ? 'negative' : 'positive',
                    'direction' => (int) $summary['out_of_stock_count'] > 0 ? 'up' : 'flat',
                ],
            ],
            'revenuePeriod' => $revenuePeriod,
            'currentSales' => $this->currentSales($summary['recent_sales'], $currency),
            'mostSoldParts' => $topParts,
            'salesTrend' => $salesTrend,
            'branchPerformance' => $branchPerformance,
            'branchSalesMix' => $this->branchSalesMix($branchPerformance),
            'inventoryValueComparison' => $this->inventoryValueComparison(
                $currency,
                (float) $summary['total_stock_value'],
                $this->dashboard->totalStockRetailValue($siteId, $siteIds)
            ),
            'balanceExposureComparison' => $this->balanceExposureComparison(
                $currency,
                (float) $summary['outstanding_customer_balances'],
                (float) $summary['outstanding_supplier_balances']
            ),
            'lossRisks' => $this->lossRisks($currency, $siteId, $siteIds),
            'stockAlerts' => $this->stockAlerts($siteId, $siteIds),
            'priorityActions' => $this->priorityActions(
                $summary,
                $priorityActionSummary,
                $currency,
                $siteId,
                $user
            ),
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

    private function topParts(string $currency, ?int $siteId = null, ?array $siteIds = null): Collection
    {
        $rows = $this->dashboard->topSellingProducts([
            'date_from' => today()->subDays(29)->toDateString(),
            'date_to' => today()->toDateString(),
            ...($siteId ? ['site_id' => $siteId] : []),
            ...($siteIds !== null ? ['site_ids' => $siteIds] : []),
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

    private function salesTrend(string $currency, int $days = 7, ?int $siteId = null, ?array $siteIds = null): Collection
    {
        $rows = $this->dashboard->salesTrend(days: $days, siteId: $siteId, siteIds: $siteIds);
        $maxSales = max((float) $rows->max('sales_amount'), 1);

        return $rows
            ->map(fn (array $row, int $index) => [
                'label' => Carbon::parse($row['date'])->format($days > 7 ? 'M j' : 'D'),
                'value' => $this->formatCurrency((float) $row['sales_amount'], $currency, true),
                'height' => (float) $row['sales_amount'] > 0
                    ? max(8, (int) round(((float) $row['sales_amount'] / $maxSales) * 100))
                    : 2,
                'show_detail' => $days <= 7
                    || $index === $days - 1
                    || $index % ($days <= 14 ? 2 : 5) === 0,
            ])
            ->values();
    }

    private function inventoryValueComparison(string $currency, float $costValue, float $retailValue): Collection
    {
        $maximum = max($costValue, $retailValue, 1);

        return collect([
            [
                'label' => 'At purchase cost',
                'value' => $this->formatCurrency($costValue, $currency, true),
                'width' => $this->comparisonWidth($costValue, $maximum),
                'tone' => 'neutral',
            ],
            [
                'label' => 'At selling price',
                'value' => $this->formatCurrency($retailValue, $currency, true),
                'width' => $this->comparisonWidth($retailValue, $maximum),
                'tone' => 'primary',
            ],
        ]);
    }

    private function balanceExposureComparison(string $currency, float $debtorValue, float $creditorValue): Collection
    {
        $maximum = max($debtorValue, $creditorValue, 1);

        return collect([
            [
                'label' => 'Debtors owe us',
                'value' => $this->formatCurrency($debtorValue, $currency, true),
                'width' => $this->comparisonWidth($debtorValue, $maximum),
                'tone' => 'primary',
            ],
            [
                'label' => 'We owe suppliers',
                'value' => $this->formatCurrency($creditorValue, $currency, true),
                'width' => $this->comparisonWidth($creditorValue, $maximum),
                'tone' => 'neutral',
            ],
        ]);
    }

    private function comparisonWidth(float $value, float $maximum): int
    {
        return $value > 0 ? max(3, (int) round(($value / $maximum) * 100)) : 0;
    }

    private function branchPerformance(string $currency, ?int $siteId = null, ?array $siteIds = null): Collection
    {
        $rows = $this->dashboard->branchPerformance([
            'date_from' => today()->toDateString(),
            'date_to' => today()->toDateString(),
            ...($siteId ? ['site_id' => $siteId] : []),
            ...($siteIds !== null ? ['site_ids' => $siteIds] : []),
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

    private function priorityActions(
        array $summary,
        array $actionSummary,
        string $currency,
        ?int $siteId,
        ?User $user
    ): Collection {
        $tasks = collect();
        $siteParameters = $siteId ? ['site_id' => $siteId] : [];
        $can = fn (string $permission): bool => $user === null || $user->hasPermission($permission);
        $push = function (
            int $count,
            string $noun,
            string $singularAction,
            string $pluralAction,
            string $detail,
            string $tone,
            string $mark,
            string $action,
            string $route,
            array $routeParameters = []
        ) use ($tasks): void {
            if ($count <= 0) {
                return;
            }

            $tasks->push([
                'title' => $this->taskTitle($count, $noun, $singularAction, $pluralAction),
                'detail' => $detail,
                'tone' => $tone,
                'mark' => $mark,
                'action' => $action,
                'route' => $route,
                'route_parameters' => $routeParameters,
            ]);
        };

        $push(
            (int) $summary['out_of_stock_count'],
            'part',
            'is out of stock',
            'are out of stock',
            'Sales and customer orders may be delayed',
            'danger',
            '!',
            'Review',
            'web.catalog.products.index',
            [...$siteParameters, 'stock_status' => 'out']
        );

        $push(
            (int) $actionSummary['below_minimum_stock'],
            'part',
            'is below its reorder level',
            'are below their reorder level',
            'Replenish stock before the remaining units run out',
            'warning',
            '↓',
            'Review',
            'web.alerts.index',
            $siteParameters
        );

        if ($can('stock.view')) {
            $push(
                (int) $actionSummary['pending_transfers'],
                'transfer',
                'is awaiting action',
                'are awaiting action',
                'Draft or pending transfers need operational review',
                'info',
                '↔',
                'Track',
                'web.catalog.sites.transfers.index',
                $siteParameters
            );

            $push(
                (int) $actionSummary['stock_take_variances'],
                'stock count',
                'has a recorded variance',
                'have recorded variances',
                'Counted quantities differ from system stock',
                'info',
                '±',
                'Review',
                'web.catalog.sites.stock-takes.index',
                $siteParameters
            );
        }

        if ($can('purchases.view')) {
            $push(
                (int) $actionSummary['pending_purchase_orders'],
                'purchase order',
                'is awaiting completion',
                'are awaiting completion',
                'Draft or pending purchases have not been received',
                'warning',
                '⌛',
                'Review',
                'web.purchases.index'
            );

            $push(
                (int) $actionSummary['supplier_invoices_outstanding'],
                'supplier invoice',
                'has an outstanding balance',
                'have outstanding balances',
                $this->formatCurrency((float) $summary['outstanding_supplier_balances'], $currency, true).' remains payable',
                'finance',
                '↗',
                'Review',
                'web.purchases.index'
            );
        } elseif ($can('reports.view')) {
            $push(
                (int) $actionSummary['supplier_invoices_outstanding'],
                'supplier invoice',
                'has an outstanding balance',
                'have outstanding balances',
                $this->formatCurrency((float) $summary['outstanding_supplier_balances'], $currency, true).' remains payable',
                'finance',
                '↗',
                'Review',
                'web.reports.index',
                $siteParameters
            );
        }

        if ($can('sales.view')) {
            $push(
                (int) $actionSummary['customer_invoices_outstanding'],
                'customer invoice',
                'needs payment follow-up',
                'need payment follow-up',
                $this->formatCurrency((float) $summary['outstanding_customer_balances'], $currency, true).' remains collectible',
                'finance',
                '↙',
                'Collect',
                'web.sales.index'
            );
        } elseif ($can('reports.view')) {
            $push(
                (int) $actionSummary['customer_invoices_outstanding'],
                'customer invoice',
                'needs payment follow-up',
                'need payment follow-up',
                $this->formatCurrency((float) $summary['outstanding_customer_balances'], $currency, true).' remains collectible',
                'finance',
                '↙',
                'Review',
                'web.reports.index',
                $siteParameters
            );
        }

        return $tasks->values();
    }

    private function stockAlerts(?int $siteId = null, ?array $siteIds = null): Collection
    {
        return $this->dashboard->lowStockAlerts(filters: [
            ...($siteId ? ['site_id' => $siteId] : []),
            ...($siteIds !== null ? ['site_ids' => $siteIds] : []),
        ])
            ->take(5)
            ->map(fn ($row) => [
                'part' => $row->product_name,
                'branch' => $row->site_name,
                'available' => (int) $row->available_quantity,
                'recommended' => (int) $row->low_stock_level,
                'priority_tone' => ((int) $row->available_quantity <= 0) ? 'danger' : 'warning',
            ])
            ->values();
    }

    private function taskTitle(
        int $count,
        string $noun,
        string $singularAction,
        string $pluralAction
    ): string {
        return $count.' '.str($noun)->plural($count).' '.($count === 1 ? $singularAction : $pluralAction);
    }

    private function branchSalesMix(Collection $branchPerformance): Collection
    {
        $colors = ['#1f3a5f', '#4f7cad', '#7fb4d8', '#91c7a9', '#d7a75f', '#b96b6b'];
        $totalSales = (float) $branchPerformance->sum('sales_raw');

        if ($totalSales <= 0) {
            return collect();
        }

        return $branchPerformance
            ->filter(fn (array $branch): bool => (float) $branch['sales_raw'] > 0)
            ->values()
            ->map(function (array $branch, int $index) use ($totalSales, $colors): array {
                $share = ((float) $branch['sales_raw'] / $totalSales) * 100;

                return [
                    'branch' => $branch['branch'],
                    'sales' => $branch['sales'],
                    'share' => round($share, 1),
                    'color' => $colors[$index % count($colors)],
                    'selected' => $branch['selected'],
                ];
            });
    }

    private function lossRisks(string $currency, ?int $siteId = null, ?array $siteIds = null): Collection
    {
        return collect([
            [
                'label' => 'Purchase returns',
                'value' => $this->formatCurrency($this->dashboard->pendingPurchaseReturnTotal($siteId, $siteIds), $currency, true),
                'detail' => 'Open supplier return documents awaiting completion',
            ],
            [
                'label' => 'Stock take variance',
                'value' => $this->formatCurrency($this->dashboard->stockTakeVarianceTotal($siteId, $siteIds), $currency, true),
                'detail' => 'Recorded variance value from stock counts',
            ],
            [
                'label' => 'Customer balances',
                'value' => $this->formatCurrency($this->dashboard->outstandingCustomerBalances($siteId, $siteIds), $currency, true),
                'detail' => 'Unpaid or partially paid sales balances',
            ],
        ]);
    }

    private function lossExposure(?int $siteId = null, ?array $siteIds = null): float
    {
        return $this->dashboard->pendingPurchaseReturnTotal($siteId, $siteIds)
            + $this->dashboard->stockTakeVarianceTotal($siteId, $siteIds)
            + $this->dashboard->outstandingCustomerBalances($siteId, $siteIds);
    }

    private function branchOptions(?array $siteIds = null): Collection
    {
        return Site::query()
            ->active()
            ->when($siteIds !== null, fn ($query) => $query->whereIn('id', $siteIds))
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

    private function salesComparison(float $currentMonthSales, float $previousMonthSales): array
    {
        if ($previousMonthSales <= 0) {
            return $currentMonthSales > 0
                ? ['label' => 'Sales recorded this month', 'trend' => 'positive', 'direction' => 'up']
                : ['label' => 'No completed sales this month', 'trend' => 'neutral', 'direction' => 'flat'];
        }

        $change = (($currentMonthSales - $previousMonthSales) / $previousMonthSales) * 100;

        if (abs($change) < 0.05) {
            return ['label' => 'Unchanged from last month', 'trend' => 'neutral', 'direction' => 'flat'];
        }

        return [
            'label' => number_format(abs($change), 1).'% '.($change > 0 ? 'increase' : 'decrease').' from last month',
            'trend' => $change > 0 ? 'positive' : 'negative',
            'direction' => $change > 0 ? 'up' : 'down',
        ];
    }

    private function marginPercent(float $profit, float $sales): float
    {
        return $sales > 0 ? ($profit / $sales) * 100 : 0;
    }
}
