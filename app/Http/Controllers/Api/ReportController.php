<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpenseResource;
use App\Http\Resources\InventoryDocumentResource;
use App\Http\Resources\StockMovementResource;
use App\Services\ReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService
    ) {}

    public function currentStockBySite(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->currentStockBySite($request->query()), 'Current stock report retrieved successfully');
    }

    public function lowStockBySite(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->lowStockBySite($request->query()), 'Low stock report retrieved successfully');
    }

    public function outOfStockProducts(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->outOfStockProducts($request->query()), 'Out of stock report retrieved successfully');
    }

    public function stockValuation(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->stockValuation($request->query()), 'Stock valuation report retrieved successfully');
    }

    public function mostSellingProducts(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->mostSellingProducts($request->query()), 'Most selling products report retrieved successfully');
    }

    public function leastSellingProducts(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->leastSellingProducts($request->query()), 'Least selling products report retrieved successfully');
    }

    public function salesByDateRange(Request $request): JsonResponse
    {
        return ApiResponse::success(InventoryDocumentResource::collection($this->reportService->salesByDateRange($request->query())), 'Sales report retrieved successfully');
    }

    public function purchasesByDateRange(Request $request): JsonResponse
    {
        return ApiResponse::success(InventoryDocumentResource::collection($this->reportService->purchasesByDateRange($request->query())), 'Purchases report retrieved successfully');
    }

    public function profitByProduct(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->profitByProduct($request->query()), 'Profit by product report retrieved successfully');
    }

    public function profitBySite(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->profitBySite($request->query()), 'Profit by site report retrieved successfully');
    }

    public function customerBalances(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->customerBalances($request->query()), 'Customer balances report retrieved successfully');
    }

    public function paymentsByAccount(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->paymentsByAccount($request->query()), 'Payments by account report retrieved successfully');
    }

    public function stockMovementHistory(Request $request): JsonResponse
    {
        return ApiResponse::success(StockMovementResource::collection($this->reportService->stockMovementHistory($request->query())), 'Stock movement history retrieved successfully');
    }

    public function stockTransferHistory(Request $request): JsonResponse
    {
        return ApiResponse::success(InventoryDocumentResource::collection($this->reportService->stockTransferHistory($request->query())), 'Stock transfer history retrieved successfully');
    }

    public function stockTakeVariance(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->stockTakeVariance($request->query()), 'Stock take variance report retrieved successfully');
    }

    public function expenses(Request $request): JsonResponse
    {
        return ApiResponse::success(ExpenseResource::collection($this->reportService->expenses($request->query())), 'Expenses report retrieved successfully');
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
        $rows = $this->reportService->fullFieldReportRows($reportType, array_filter($filters, fn ($value) => $value !== null && $value !== ''));
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
}
