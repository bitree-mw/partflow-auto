<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryDocument\StoreSaleReturnRequest;
use App\Http\Resources\InventoryDocumentResource;
use App\Models\InventoryDocument;
use App\Services\InventoryDocumentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleReturnController extends Controller
{
    public function __construct(
        private readonly InventoryDocumentService $inventoryDocumentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $returns = $this->inventoryDocumentService->listByType('sale_return', $request->query(), $request->user());

        return ApiResponse::success(InventoryDocumentResource::collection($returns), 'Sale returns retrieved successfully');
    }

    public function store(StoreSaleReturnRequest $request): JsonResponse
    {
        $return = $this->inventoryDocumentService->createSaleReturn($request->validated(), $request->user());

        return ApiResponse::created(new InventoryDocumentResource($return), 'Sale return created successfully');
    }

    public function show(Request $request, InventoryDocument $inventoryDocument): JsonResponse
    {
        return ApiResponse::success(
            new InventoryDocumentResource($this->inventoryDocumentService->show($inventoryDocument, $request->user())),
            'Sale return retrieved successfully'
        );
    }
}
