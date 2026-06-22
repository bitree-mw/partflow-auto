<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\RoleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $roles = $this->roleService->list($request->query());

        return ApiResponse::success(
            data: RoleResource::collection($roles),
            message: 'Roles retrieved successfully'
        );
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->roleService->create($request->validated());

        return ApiResponse::created(
            data: new RoleResource($role),
            message: 'Role created successfully'
        );
    }

    public function show(Role $role): JsonResponse
    {
        $role->loadCount('users');

        return ApiResponse::success(
            data: new RoleResource($role),
            message: 'Role retrieved successfully'
        );
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $role = $this->roleService->update($role, $request->validated());

        return ApiResponse::updated(
            data: new RoleResource($role),
            message: 'Role updated successfully'
        );
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->roleService->delete($role);

        return ApiResponse::deleted('Role deleted successfully');
    }
}
