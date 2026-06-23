<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryDocument\StorePurchaseReturnRequest;
use App\Http\Resources\InventoryDocumentResource;
use App\Models\InventoryDocument;
use App\Services\InventoryDocumentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseReturnController extends Controller
{
    public function __construct(
        private readonly InventoryDocumentService $inventoryDocumentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $returns = $this->inventoryDocumentService->listByType('purchase_return', $request->query());

        return ApiResponse::success(InventoryDocumentResource::collection($returns), 'Purchase returns retrieved successfully');
    }

    public function store(StorePurchaseReturnRequest $request): JsonResponse
    {
        $return = $this->inventoryDocumentService->createPurchaseReturn($request->validated(), $request->user());

        return ApiResponse::created(new InventoryDocumentResource($return), 'Purchase return created successfully');
    }

    public function show(InventoryDocument $inventoryDocument): JsonResponse
    {
        return ApiResponse::success(
            new InventoryDocumentResource($this->inventoryDocumentService->show($inventoryDocument)),
            'Purchase return retrieved successfully'
        );
    }
}
