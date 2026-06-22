<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Services\BrandService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function __construct(
        private readonly BrandService $brandService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $brands = $this->brandService->list($request->query());

        return ApiResponse::success(
            data: BrandResource::collection($brands),
            message: 'Brands retrieved successfully'
        );
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        $brand = $this->brandService->create($request->validated());

        return ApiResponse::created(
            data: new BrandResource($brand),
            message: 'Brand created successfully'
        );
    }

    public function show(Brand $brand): JsonResponse
    {
        return ApiResponse::success(
            data: new BrandResource($brand),
            message: 'Brand retrieved successfully'
        );
    }

    public function update(UpdateBrandRequest $request, Brand $brand): JsonResponse
    {
        $brand = $this->brandService->update($brand, $request->validated());

        return ApiResponse::updated(
            data: new BrandResource($brand),
            message: 'Brand updated successfully'
        );
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $this->brandService->delete($brand);

        return ApiResponse::deleted('Brand deleted successfully');
    }
}
