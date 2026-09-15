<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PosProductResource;
use App\Services\DiscountPolicyService;
use App\Services\PosProductSearchService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosProductController extends Controller
{
    public function __construct(
        private readonly PosProductSearchService $posProductSearchService,
        private readonly DiscountPolicyService $discountPolicy
    ) {}

    public function index(Request $request): JsonResponse
    {
        $products = $this->posProductSearchService->search($request->query(), $request->user());

        return ApiResponse::success(
            PosProductResource::collection($products),
            'POS products retrieved successfully',
            meta: ['maximum_discount_percentage' => $this->discountPolicy->maximumDiscountPercentage()]
        );
    }

    public function suggestions(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->posProductSearchService->suggestions($request->query(), $request->user()),
            'POS suggestions retrieved successfully'
        );
    }
}
