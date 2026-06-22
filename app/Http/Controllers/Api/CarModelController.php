<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CarModel\StoreCarModelRequest;
use App\Http\Requests\CarModel\UpdateCarModelRequest;
use App\Http\Resources\CarModelResource;
use App\Models\CarModel;
use App\Services\CarModelService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarModelController extends Controller
{
    public function __construct(
        private readonly CarModelService $carModelService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $carModels = $this->carModelService->list($request->query());

        return ApiResponse::success(
            data: CarModelResource::collection($carModels),
            message: 'Car models retrieved successfully'
        );
    }

    public function store(StoreCarModelRequest $request): JsonResponse
    {
        $carModel = $this->carModelService->create($request->validated());

        return ApiResponse::created(
            data: new CarModelResource($carModel),
            message: 'Car model created successfully'
        );
    }

    public function show(CarModel $car_model): JsonResponse
    {
        return ApiResponse::success(
            data: new CarModelResource($car_model),
            message: 'Car model retrieved successfully'
        );
    }

    public function update(UpdateCarModelRequest $request, CarModel $car_model): JsonResponse
    {
        $carModel = $this->carModelService->update($car_model, $request->validated());

        return ApiResponse::updated(
            data: new CarModelResource($carModel),
            message: 'Car model updated successfully'
        );
    }

    public function destroy(CarModel $car_model): JsonResponse
    {
        $this->carModelService->delete($car_model);

        return ApiResponse::deleted('Car model deleted successfully');
    }
}
