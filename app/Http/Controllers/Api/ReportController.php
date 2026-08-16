<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpenseResource;
use App\Http\Resources\InventoryDocumentResource;
use App\Http\Resources\StockMovementResource;
use App\Services\ReportService;
use App\Services\SiteAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly SiteAccessService $siteAccessService
    ) {}

    public function currentStockBySite(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->currentStockBySite($this->filters($request)), 'Current stock report retrieved successfully');
    }

    public function lowStockBySite(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->lowStockBySite($this->filters($request)), 'Low stock report retrieved successfully');
    }

    public function outOfStockProducts(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->outOfStockProducts($this->filters($request)), 'Out of stock report retrieved successfully');
    }

    public function stockValuation(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->stockValuation($this->filters($request)), 'Stock valuation report retrieved successfully');
    }

    public function mostSellingProducts(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->mostSellingProducts($this->filters($request)), 'Most selling products report retrieved successfully');
    }

    public function leastSellingProducts(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->leastSellingProducts($this->filters($request)), 'Least selling products report retrieved successfully');
    }

    public function salesByDateRange(Request $request): JsonResponse
    {
        return ApiResponse::success(InventoryDocumentResource::collection($this->reportService->salesByDateRange($this->filters($request))), 'Sales report retrieved successfully');
    }

    public function purchasesByDateRange(Request $request): JsonResponse
    {
        return ApiResponse::success(InventoryDocumentResource::collection($this->reportService->purchasesByDateRange($this->filters($request))), 'Purchases report retrieved successfully');
    }

    public function profitByProduct(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->profitByProduct($this->filters($request)), 'Profit by product report retrieved successfully');
    }

    public function profitBySite(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->profitBySite($this->filters($request)), 'Profit by site report retrieved successfully');
    }

    public function customerBalances(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->customerBalances($this->filters($request)), 'Customer balances report retrieved successfully');
    }

    public function paymentsByAccount(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->paymentsByAccount($this->filters($request)), 'Payments by account report retrieved successfully');
    }

    public function stockMovementHistory(Request $request): JsonResponse
    {
        return ApiResponse::success(StockMovementResource::collection($this->reportService->stockMovementHistory($this->filters($request))), 'Stock movement history retrieved successfully');
    }

    public function stockTransferHistory(Request $request): JsonResponse
    {
        return ApiResponse::success(InventoryDocumentResource::collection($this->reportService->stockTransferHistory($this->filters($request))), 'Stock transfer history retrieved successfully');
    }

    public function stockTakeVariance(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->stockTakeVariance($this->filters($request)), 'Stock take variance report retrieved successfully');
    }

    public function expenses(Request $request): JsonResponse
    {
        return ApiResponse::success(ExpenseResource::collection($this->reportService->expenses($this->filters($request))), 'Expenses report retrieved successfully');
    }

    public function export(Request $request): StreamedResponse
    {
        $reportType = (string) $request->query('report_type', 'sales');
        $filters = $request->only([
            'date_from',
            'date_to',
            'site_id',
            'status',
            'product_id',
            'contact_id',
            'payment_account_id',
            'expense_category_id',
        ]);
        $filters = $this->siteAccessService->scopeFilters(
            $request->user(),
            array_filter($filters, fn ($value) => $value !== null && $value !== '')
        );
        $rows = $this->reportService->fullFieldReportRows($reportType, $filters);
        $normalizedType = $this->reportService->normalizeReportType($reportType);
        $filename = sprintf(
            '%s_%s_to_%s.csv',
            $normalizedType,
            $request->query('date_from', 'start'),
            $request->query('date_to', 'end')
        );

        return response()->streamDownload(function () use ($rows, $normalizedType, $filters): void {
            $handle = fopen('php://output', 'w');
            $headers = $this->csvHeaders($rows, $normalizedType, $filters);

            fputcsv($handle, $headers);

            if ($rows->isEmpty()) {
                fputcsv($handle, [
                    $normalizedType,
                    $filters['date_from'] ?? null,
                    $filters['date_to'] ?? null,
                    'No transactions found for the selected filters.',
                ]);
            }

            foreach ($rows as $row) {
                $row = (array) $row;
                fputcsv($handle, array_map(fn ($header) => $row[$header] ?? null, $headers));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function csvHeaders($rows, string $normalizedType, array $filters): array
    {
        if ($rows->isNotEmpty()) {
            return array_keys((array) $rows->first());
        }

        return [
            'report_type',
            'date_from',
            'date_to',
            'message',
        ];
    }

    private function filters(Request $request): array
    {
        return $this->siteAccessService->scopeFilters($request->user(), $request->query());
    }
}
