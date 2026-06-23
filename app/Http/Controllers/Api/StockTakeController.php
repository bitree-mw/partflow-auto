<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryDocument\StoreStockTakeRequest;
use App\Http\Resources\InventoryDocumentResource;
use App\Models\InventoryDocument;
use App\Services\InventoryDocumentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockTakeController extends Controller
{
    public function __construct(
        private readonly InventoryDocumentService $inventoryDocumentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $stockTakes = $this->inventoryDocumentService->listByType('stock_take', $request->query());

        return ApiResponse::success(InventoryDocumentResource::collection($stockTakes), 'Stock takes retrieved successfully');
    }

    public function store(StoreStockTakeRequest $request): JsonResponse
    {
        $stockTake = $this->inventoryDocumentService->createStockTake($request->validated(), $request->user());

        return ApiResponse::created(new InventoryDocumentResource($stockTake), 'Stock take created successfully');
    }

    public function show(InventoryDocument $inventoryDocument): JsonResponse
    {
        return ApiResponse::success(
            new InventoryDocumentResource($this->inventoryDocumentService->show($inventoryDocument)),
            'Stock take retrieved successfully'
        );
    }
}
