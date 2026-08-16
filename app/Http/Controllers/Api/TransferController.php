<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryDocument\StoreTransferRequest;
use App\Http\Resources\InventoryDocumentResource;
use App\Models\InventoryDocument;
use App\Services\InventoryDocumentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function __construct(
        private readonly InventoryDocumentService $inventoryDocumentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $transfers = $this->inventoryDocumentService->listByType('transfer', $request->query(), $request->user());

        return ApiResponse::success(InventoryDocumentResource::collection($transfers), 'Transfers retrieved successfully');
    }

    public function store(StoreTransferRequest $request): JsonResponse
    {
        $transfer = $this->inventoryDocumentService->createTransfer($request->validated(), $request->user());

        return ApiResponse::created(new InventoryDocumentResource($transfer), 'Transfer created successfully');
    }

    public function show(Request $request, InventoryDocument $inventoryDocument): JsonResponse
    {
        return ApiResponse::success(
            new InventoryDocumentResource($this->inventoryDocumentService->show($inventoryDocument, $request->user())),
            'Transfer retrieved successfully'
        );
    }
}
