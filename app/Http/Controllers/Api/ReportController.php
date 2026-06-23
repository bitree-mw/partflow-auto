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
}
