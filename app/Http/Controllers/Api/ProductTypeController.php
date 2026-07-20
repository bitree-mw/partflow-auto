<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductType\StoreProductTypeRequest;
use App\Http\Requests\ProductType\UpdateProductTypeRequest;
use App\Http\Resources\ProductTypeResource;
use App\Models\ProductType;
use App\Services\ProductTypeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductTypeController extends Controller
{
    public function __construct(
        private readonly ProductTypeService $productTypeService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $productTypes = $this->productTypeService->list($request->query());

        return ApiResponse::success(
            data: ProductTypeResource::collection($productTypes),
            message: 'Product types retrieved successfully'
        );
    }

    public function store(StoreProductTypeRequest $request): JsonResponse
    {
        $productType = $this->productTypeService->create($request->validated());

        return ApiResponse::created(
            data: new ProductTypeResource($productType),
            message: 'Product type created successfully'
        );
    }

    public function show(ProductType $product_type): JsonResponse
    {
        return ApiResponse::success(
            data: new ProductTypeResource($product_type),
            message: 'Product type retrieved successfully'
        );
    }

    public function update(UpdateProductTypeRequest $request, ProductType $product_type): JsonResponse
    {
        $productType = $this->productTypeService->update($product_type, $request->validated());

        return ApiResponse::updated(
            data: new ProductTypeResource($productType),
            message: 'Product type updated successfully'
        );
    }

    public function destroy(ProductType $product_type): JsonResponse
    {
        $this->productTypeService->delete($product_type);

        return ApiResponse::deleted('Product type deleted successfully');
    }
}
