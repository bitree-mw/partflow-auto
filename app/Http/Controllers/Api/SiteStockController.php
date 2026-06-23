<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SiteStock\StoreSiteStockRequest;
use App\Http\Requests\SiteStock\UpdateSiteStockRequest;
use App\Http\Resources\SiteStockResource;
use App\Models\SiteStock;
use App\Services\SiteStockService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteStockController extends Controller
{
    public function __construct(
        private readonly SiteStockService $siteStockService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $siteStocks = $this->siteStockService->list($request->query());

        return ApiResponse::success(
            data: SiteStockResource::collection($siteStocks),
            message: 'Site stocks retrieved successfully'
        );
    }

    public function store(StoreSiteStockRequest $request): JsonResponse
    {
        $siteStock = $this->siteStockService->create($request->validated());

        return ApiResponse::created(
            data: new SiteStockResource($siteStock),
            message: 'Site stock created successfully'
        );
    }

    public function show(SiteStock $siteStock): JsonResponse
    {
        $siteStock->load([
            'product.carModel',
            'product.partType',
            'product.fuelType',
            'product.brand',
            'site',
        ]);

        return ApiResponse::success(
            data: new SiteStockResource($siteStock),
            message: 'Site stock retrieved successfully'
        );
    }

    public function update(UpdateSiteStockRequest $request, SiteStock $siteStock): JsonResponse
    {
        $siteStock = $this->siteStockService->update(
            siteStock: $siteStock,
            data: $request->validated()
        );

        return ApiResponse::updated(
            data: new SiteStockResource($siteStock),
            message: 'Site stock updated successfully'
        );
    }

    public function destroy(SiteStock $siteStock): JsonResponse
    {
        $this->siteStockService->delete($siteStock);

        return ApiResponse::deleted('Site stock deleted successfully');
    }
}
