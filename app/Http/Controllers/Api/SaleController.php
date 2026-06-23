<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryDocument\StoreSaleRequest;
use App\Http\Resources\InventoryDocumentResource;
use App\Models\InventoryDocument;
use App\Services\InventoryDocumentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(
        private readonly InventoryDocumentService $inventoryDocumentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $sales = $this->inventoryDocumentService->listByType('sale', $request->query());

        return ApiResponse::success(InventoryDocumentResource::collection($sales), 'Sales retrieved successfully');
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        $sale = $this->inventoryDocumentService->createSale($request->validated(), $request->user());

        return ApiResponse::created(new InventoryDocumentResource($sale), 'Sale created successfully');
    }

    public function show(InventoryDocument $inventoryDocument): JsonResponse
    {
        return ApiResponse::success(
            new InventoryDocumentResource($this->inventoryDocumentService->show($inventoryDocument)),
            'Sale retrieved successfully'
        );
    }
}
