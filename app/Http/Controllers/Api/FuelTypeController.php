<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FuelType\StoreFuelTypeRequest;
use App\Http\Requests\FuelType\UpdateFuelTypeRequest;
use App\Http\Resources\FuelTypeResource;
use App\Models\FuelType;
use App\Services\FuelTypeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FuelTypeController extends Controller
{
    public function __construct(
        private readonly FuelTypeService $fuelTypeService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $fuelTypes = $this->fuelTypeService->list($request->query());

        return ApiResponse::success(
            data: FuelTypeResource::collection($fuelTypes),
            message: 'Fuel types retrieved successfully'
        );
    }

    public function store(StoreFuelTypeRequest $request): JsonResponse
    {
        $fuelType = $this->fuelTypeService->create($request->validated());

        return ApiResponse::created(
            data: new FuelTypeResource($fuelType),
            message: 'Fuel type created successfully'
        );
    }

    public function show(FuelType $fuel_type): JsonResponse
    {
        return ApiResponse::success(
            data: new FuelTypeResource($fuel_type),
            message: 'Fuel type retrieved successfully'
        );
    }

    public function update(UpdateFuelTypeRequest $request, FuelType $fuel_type): JsonResponse
    {
        $fuelType = $this->fuelTypeService->update($fuel_type, $request->validated());

        return ApiResponse::updated(
            data: new FuelTypeResource($fuelType),
            message: 'Fuel type updated successfully'
        );
    }

    public function destroy(FuelType $fuel_type): JsonResponse
    {
        $this->fuelTypeService->delete($fuel_type);

        return ApiResponse::deleted('Fuel type deleted successfully');
    }
}
