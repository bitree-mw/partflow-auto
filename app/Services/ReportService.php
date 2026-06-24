<?php

namespace App\Services;

use App\Repositories\ReportRepository;
use Illuminate\Support\Collection;

class ReportService
{
    public function __construct(
        private readonly ReportRepository $reports
    ) {}

    public function currentStockBySite(array $filters = []): Collection
    {
        return $this->reports->currentStockBySite($filters);
    }

    public function lowStockBySite(array $filters = []): Collection
    {
        return $this->reports->lowStockBySite($filters);
    }

    public function outOfStockProducts(array $filters = []): Collection
    {
        return $this->reports->outOfStockProducts($filters);
    }

    public function stockValuation(array $filters = []): Collection
    {
        return $this->reports->stockValuation($filters);
    }

    public function mostSellingProducts(array $filters = []): Collection
    {
        return $this->reports->mostSellingProducts($filters);
    }

    public function leastSellingProducts(array $filters = []): Collection
    {
        return $this->reports->leastSellingProducts($filters);
    }

    public function salesByDateRange(array $filters = []): Collection
    {
        return $this->reports->salesByDateRange($filters);
    }

    public function purchasesByDateRange(array $filters = []): Collection
    {
        return $this->reports->purchasesByDateRange($filters);
    }

    public function profitByProduct(array $filters = []): Collection
    {
        return $this->reports->profitByProduct($filters);
    }

    public function profitBySite(array $filters = []): Collection
    {
        return $this->reports->profitBySite($filters);
    }

    public function customerBalances(array $filters = []): Collection
    {
        return $this->reports->customerBalances($filters);
    }

    public function paymentsByAccount(array $filters = []): Collection
    {
        return $this->reports->paymentsByAccount($filters);
    }

    public function stockMovementHistory(array $filters = []): Collection
    {
        return $this->reports->stockMovementHistory($filters);
    }

    public function stockTransferHistory(array $filters = []): Collection
    {
        return $this->reports->stockTransferHistory($filters);
    }

    public function stockTakeVariance(array $filters = []): Collection
    {
        return $this->reports->stockTakeVariance($filters);
    }

    public function expenses(array $filters = []): Collection
    {
        return $this->reports->expenses($filters);
    }

    public function fullFieldReportRows(string $reportType, array $filters = []): Collection
    {
        return $this->reports->fullFieldReportRows($reportType, $filters);
    }

    public function normalizeReportType(string $reportType): string
    {
        return $this->reports->normalizeReportType($reportType);
    }
}
