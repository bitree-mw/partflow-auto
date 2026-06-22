<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserSiteAccess\StoreUserSiteAccessRequest;
use App\Http\Requests\UserSiteAccess\UpdateUserSiteAccessRequest;
use App\Http\Resources\UserSiteAccessResource;
use App\Models\UserSiteAccess;
use App\Services\UserSiteAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSiteAccessController extends Controller
{
    public function __construct(
        private readonly UserSiteAccessService $userSiteAccessService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $accessList = $this->userSiteAccessService->list($request->query());

        return ApiResponse::success(
            data: UserSiteAccessResource::collection($accessList),
            message: 'User site access records retrieved successfully'
        );
    }

    public function store(StoreUserSiteAccessRequest $request): JsonResponse
    {
        $access = $this->userSiteAccessService->create($request->validated());

        return ApiResponse::created(
            data: new UserSiteAccessResource($access),
            message: 'User site access created successfully'
        );
    }

    public function show(UserSiteAccess $user_site_access): JsonResponse
    {
        $user_site_access->load(['user.role', 'site']);

        return ApiResponse::success(
            data: new UserSiteAccessResource($user_site_access),
            message: 'User site access retrieved successfully'
        );
    }

    public function update(
        UpdateUserSiteAccessRequest $request,
        UserSiteAccess $user_site_access
    ): JsonResponse {
        $access = $this->userSiteAccessService->update(
            $user_site_access,
            $request->validated()
        );

        return ApiResponse::updated(
            data: new UserSiteAccessResource($access),
            message: 'User site access updated successfully'
        );
    }

    public function destroy(UserSiteAccess $user_site_access): JsonResponse
    {
        $this->userSiteAccessService->delete($user_site_access);

        return ApiResponse::deleted('User site access deleted successfully');
    }
}
