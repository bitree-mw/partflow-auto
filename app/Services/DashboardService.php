<?php

namespace App\Services;

use App\Repositories\DashboardRepository;

class DashboardService
{
    public function __construct(
        private readonly DashboardRepository $dashboard
    ) {}

    public function summary(): array
    {
        return [
            'today_sales' => $this->dashboard->todaySales(),
            'today_profit' => $this->dashboard->todayProfit(),
            'total_stock_value' => $this->dashboard->totalStockValue(),
            'low_stock_count' => $this->dashboard->lowStockCount(),
            'out_of_stock_count' => $this->dashboard->outOfStockCount(),
            'outstanding_customer_balances' => $this->dashboard->outstandingCustomerBalances(),
            'recent_sales' => $this->dashboard->recentDocuments('sale'),
            'recent_purchases' => $this->dashboard->recentDocuments('purchase'),
            'recent_transfers' => $this->dashboard->recentDocuments('transfer'),
            'recent_stock_movements' => $this->dashboard->recentStockMovements(),
        ];
    }
}
