<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryDocumentResource;
use App\Models\InventoryDocument;
use App\Services\InventoryDocumentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryDocumentController extends Controller
{
    public function __construct(
        private readonly InventoryDocumentService $inventoryDocumentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $documents = $this->inventoryDocumentService->list($request->query());

        return ApiResponse::success(
            data: InventoryDocumentResource::collection($documents),
            message: 'Inventory documents retrieved successfully'
        );
    }

    public function show(InventoryDocument $inventoryDocument): JsonResponse
    {
        $inventoryDocument = $this->inventoryDocumentService->show($inventoryDocument);

        return ApiResponse::success(
            data: new InventoryDocumentResource($inventoryDocument),
            message: 'Inventory document retrieved successfully'
        );
    }
}
