<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockMovementResource;
use App\Models\StockMovement;
use App\Services\StockMovementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function __construct(
        private readonly StockMovementService $stockMovementService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $stockMovements = $this->stockMovementService->list($request->query());

        return ApiResponse::success(
            StockMovementResource::collection($stockMovements),
            'Stock movements retrieved successfully'
        );
    }

    public function show(StockMovement $stockMovement): JsonResponse
    {
        $stockMovement->load([
            'product.carModel',
            'product.productType',
            'product.fuelType',
            'product.brand',
            'product.taxProfile',
            'product.references',
            'product.compatibilities.carModel',
            'site',
            'inventoryDocument',
        ]);

        return ApiResponse::success(new StockMovementResource($stockMovement), 'Stock movement retrieved successfully');
    }
}
