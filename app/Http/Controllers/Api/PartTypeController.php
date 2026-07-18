<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PartType\StorePartTypeRequest;
use App\Http\Requests\PartType\UpdatePartTypeRequest;
use App\Http\Resources\PartTypeResource;
use App\Models\PartType;
use App\Services\PartTypeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartTypeController extends Controller
{
    public function __construct(
        private readonly PartTypeService $partTypeService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $partTypes = $this->partTypeService->list($request->query());

        return ApiResponse::success(
            data: PartTypeResource::collection($partTypes),
            message: 'Product types retrieved successfully'
        );
    }

    public function store(StorePartTypeRequest $request): JsonResponse
    {
        $partType = $this->partTypeService->create($request->validated());

        return ApiResponse::created(
            data: new PartTypeResource($partType),
            message: 'Product type created successfully'
        );
    }

    public function show(PartType $part_type): JsonResponse
    {
        return ApiResponse::success(
            data: new PartTypeResource($part_type),
            message: 'Product type retrieved successfully'
        );
    }

    public function update(UpdatePartTypeRequest $request, PartType $part_type): JsonResponse
    {
        $partType = $this->partTypeService->update($part_type, $request->validated());

        return ApiResponse::updated(
            data: new PartTypeResource($partType),
            message: 'Product type updated successfully'
        );
    }

    public function destroy(PartType $part_type): JsonResponse
    {
        $this->partTypeService->delete($part_type);

        return ApiResponse::deleted('Product type deleted successfully');
    }
}
