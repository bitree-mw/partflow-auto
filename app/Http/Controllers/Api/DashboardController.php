<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryDocumentResource;
use App\Http\Resources\StockMovementResource;
use App\Services\DashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    public function summary(): JsonResponse
    {
        $summary = $this->dashboardService->summary();
        $summary['recent_sales'] = InventoryDocumentResource::collection($summary['recent_sales']);
        $summary['recent_purchases'] = InventoryDocumentResource::collection($summary['recent_purchases']);
        $summary['recent_transfers'] = InventoryDocumentResource::collection($summary['recent_transfers']);
        $summary['recent_stock_movements'] = StockMovementResource::collection($summary['recent_stock_movements']);

        return ApiResponse::success($summary, 'Dashboard summary retrieved successfully');
    }
}
