<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryDocument\StoreStockAdjustmentRequest;
use App\Http\Resources\InventoryDocumentResource;
use App\Models\InventoryDocument;
use App\Services\InventoryDocumentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockAdjustmentController extends Controller
{
    public function __construct(
        private readonly InventoryDocumentService $inventoryDocumentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $adjustments = $this->inventoryDocumentService->listByType('adjustment', $request->query());

        return ApiResponse::success(InventoryDocumentResource::collection($adjustments), 'Stock adjustments retrieved successfully');
    }

    public function store(StoreStockAdjustmentRequest $request): JsonResponse
    {
        $adjustment = $this->inventoryDocumentService->createStockAdjustment($request->validated(), $request->user());

        return ApiResponse::created(new InventoryDocumentResource($adjustment), 'Stock adjustment created successfully');
    }

    public function show(InventoryDocument $inventoryDocument): JsonResponse
    {
        return ApiResponse::success(
            new InventoryDocumentResource($this->inventoryDocumentService->show($inventoryDocument)),
            'Stock adjustment retrieved successfully'
        );
    }
}
