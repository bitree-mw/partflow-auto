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
        $sales = $this->inventoryDocumentService->listByType('sale', $request->query(), $request->user());

        return ApiResponse::success(InventoryDocumentResource::collection($sales), 'Sales retrieved successfully');
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! $request->user()->hasPermission('sales.price_override')) {
            $data['discount_amount'] = 0;
            $data['items'] = collect($data['items'])
                ->map(function (array $item): array {
                    unset($item['unit_price'], $item['discount_amount'], $item['tax_profile_id']);

                    return $item;
                })
                ->all();
        }

        $sale = $this->inventoryDocumentService->createSale($data, $request->user());

        return ApiResponse::created(new InventoryDocumentResource($sale), 'Sale created successfully');
    }

    public function show(Request $request, InventoryDocument $inventoryDocument): JsonResponse
    {
        return ApiResponse::success(
            new InventoryDocumentResource($this->inventoryDocumentService->show($inventoryDocument, $request->user())),
            'Sale retrieved successfully'
        );
    }
}
