<?php

namespace App\Services;

use App\Repositories\ReportRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ReportService
{
    public function __construct(
        private readonly ReportRepository $reports,
        private readonly DiscountPolicyService $discountPolicy
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
        return $this->reports->stockValuation($filters, $this->discountPolicy->maximumDiscountPercentage());
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
        return $this->reports->fullFieldReportRows($reportType, [
            ...$filters,
            'maximum_discount_percentage' => $this->discountPolicy->maximumDiscountPercentage(),
        ]);
    }

    public function normalizeReportType(string $reportType): string
    {
        return $this->reports->normalizeReportType($reportType);
    }

    public function preview(string $reportType, array $filters = [], int $limit = 50): array
    {
        $normalizedType = $this->normalizeReportType($reportType);
        $columns = $this->previewColumns($normalizedType);
        $rows = $this->fullFieldReportRows($reportType, [
            ...$filters,
            'preview_limit' => $limit + 1,
        ]);
        $hasMore = $rows->count() > $limit;

        return [
            'type' => $normalizedType,
            'title' => str($normalizedType)->replace('-', ' ')->headline()->toString(),
            'columns' => collect($columns)
                ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])
                ->values(),
            'rows' => $rows
                ->take($limit)
                ->map(fn ($row): array => collect((array) $row)->only(array_keys($columns))->all())
                ->values(),
            'total' => $hasMore ? null : $rows->count(),
            'shown' => min($rows->count(), $limit),
            'has_more' => $hasMore,
        ];
    }

    public function paginated(string $reportType, array $filters = [], int $perPage = 25, int $page = 1): array
    {
        $normalizedType = $this->normalizeReportType($reportType);
        $columns = $this->previewColumns($normalizedType);
        $rows = $this->fullFieldReportRows($reportType, $filters)
            ->map(fn ($row): array => collect((array) $row)->only(array_keys($columns))->all())
            ->values();

        return [
            'type' => $normalizedType,
            'title' => str($normalizedType)->replace('-', ' ')->headline()->toString(),
            'columns' => collect($columns)
                ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])
                ->values(),
            'rows' => new LengthAwarePaginator(
                $rows->forPage($page, $perPage)->values(),
                $rows->count(),
                $perPage,
                $page,
                ['pageName' => 'page']
            ),
        ];
    }

    private function previewColumns(string $reportType): array
    {
        return match ($reportType) {
            'sales', 'sale' => [
                'document_number' => 'Sale',
                'document_date' => 'Date',
                'contact_name' => 'Customer',
                'source_site' => 'Branch',
                'product_code' => 'Part code',
                'product_name' => 'Part',
                'quantity' => 'Qty',
                'line_total' => 'Line total',
                'paid_amount' => 'Paid',
                'balance_amount' => 'Balance',
                'status' => 'Status',
            ],
            'purchases', 'purchase' => [
                'document_number' => 'Purchase',
                'document_date' => 'Date',
                'contact_name' => 'Supplier',
                'destination_site' => 'Branch',
                'product_code' => 'Part code',
                'product_name' => 'Part',
                'quantity' => 'Qty',
                'unit_cost' => 'Unit cost',
                'line_total' => 'Line total',
                'balance_amount' => 'Balance',
                'status' => 'Status',
            ],
            'inventory', 'inventory-report', 'inventory-valuation' => [
                'site_name' => 'Branch',
                'product_code' => 'Part code',
                'product_name' => 'Part',
                'quantity_on_hand' => 'On hand',
                'reserved_quantity' => 'Reserved',
                'available_quantity' => 'Available',
                'unit_purchase_cost' => 'Unit cost',
                'unit_selling_price' => 'Selling price',
                'minimum_selling_price' => 'Product minimum',
                'minimum_authorized_price' => 'Lowest authorized price',
                'stock_cost_value' => 'Stock value',
                'potential_sales_value' => 'Lowest authorized sales value',
            ],
            'creditors', 'creditor-report', 'creditor-balances' => [
                'document_number' => 'Purchase',
                'document_date' => 'Date',
                'supplier_name' => 'Supplier',
                'site_name' => 'Branch',
                'total_amount' => 'Total',
                'paid_amount' => 'Paid',
                'balance_amount' => 'Balance',
                'payment_status' => 'Payment',
            ],
            'debtors', 'debtor-report', 'debtor-balances', 'customer-balances' => [
                'document_number' => 'Sale',
                'document_date' => 'Date',
                'customer_name' => 'Customer',
                'site_name' => 'Branch',
                'total_amount' => 'Total',
                'paid_amount' => 'Paid',
                'balance_amount' => 'Balance',
                'payment_status' => 'Payment',
            ],
            default => [],
        };
    }
}
