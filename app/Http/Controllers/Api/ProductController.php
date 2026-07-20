<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $products = $this->productService->list($request->query());

        return ApiResponse::success(
            data: ProductResource::collection($products),
            message: 'Products retrieved successfully'
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->create($request->validated());

        return ApiResponse::created(
            data: new ProductResource($product),
            message: 'Product created successfully'
        );
    }

    public function show(Product $product): JsonResponse
    {
        $product->load([
            'carModel',
            'productType',
            'fuelType',
            'brand',
            'taxProfile',
            'references',
            'compatibilities.carModel',
        ]);

        return ApiResponse::success(
            data: new ProductResource($product),
            message: 'Product retrieved successfully'
        );
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->productService->update(
            product: $product,
            data: $request->validated()
        );

        return ApiResponse::updated(
            data: new ProductResource($product),
            message: 'Product updated successfully'
        );
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->productService->delete($product);

        return ApiResponse::deleted('Product deleted successfully');
    }
}
