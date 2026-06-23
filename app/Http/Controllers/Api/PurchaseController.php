<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryDocument\StorePurchaseRequest;
use App\Http\Resources\InventoryDocumentResource;
use App\Models\InventoryDocument;
use App\Services\InventoryDocumentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly InventoryDocumentService $inventoryDocumentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $purchases = $this->inventoryDocumentService->listByType('purchase', $request->query());

        return ApiResponse::success(InventoryDocumentResource::collection($purchases), 'Purchases retrieved successfully');
    }

    public function store(StorePurchaseRequest $request): JsonResponse
    {
        $purchase = $this->inventoryDocumentService->createPurchase($request->validated(), $request->user());

        return ApiResponse::created(new InventoryDocumentResource($purchase), 'Purchase created successfully');
    }

    public function show(InventoryDocument $inventoryDocument): JsonResponse
    {
        return ApiResponse::success(
            new InventoryDocumentResource($this->inventoryDocumentService->show($inventoryDocument)),
            'Purchase retrieved successfully'
        );
    }
}
