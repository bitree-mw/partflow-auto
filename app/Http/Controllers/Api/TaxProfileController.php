<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaxProfile\StoreTaxProfileRequest;
use App\Http\Requests\TaxProfile\UpdateTaxProfileRequest;
use App\Http\Resources\TaxProfileResource;
use App\Models\TaxProfile;
use App\Services\TaxProfileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxProfileController extends Controller
{
    public function __construct(
        private readonly TaxProfileService $taxProfileService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $taxProfiles = $this->taxProfileService->list($request->query());

        return ApiResponse::success(
            data: TaxProfileResource::collection($taxProfiles),
            message: 'Tax profiles retrieved successfully'
        );
    }

    public function store(StoreTaxProfileRequest $request): JsonResponse
    {
        $taxProfile = $this->taxProfileService->create($request->validated());

        return ApiResponse::created(
            data: new TaxProfileResource($taxProfile),
            message: 'Tax profile created successfully'
        );
    }

    public function show(TaxProfile $tax_profile): JsonResponse
    {
        return ApiResponse::success(
            data: new TaxProfileResource($tax_profile),
            message: 'Tax profile retrieved successfully'
        );
    }

    public function update(UpdateTaxProfileRequest $request, TaxProfile $tax_profile): JsonResponse
    {
        $taxProfile = $this->taxProfileService->update(
            $tax_profile,
            $request->validated()
        );

        return ApiResponse::updated(
            data: new TaxProfileResource($taxProfile),
            message: 'Tax profile updated successfully'
        );
    }

    public function destroy(TaxProfile $tax_profile): JsonResponse
    {
        $this->taxProfileService->delete($tax_profile);

        return ApiResponse::deleted('Tax profile deleted successfully');
    }
}
