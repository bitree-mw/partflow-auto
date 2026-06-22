<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\AuthResource;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $authData = $this->authService->register($request->validated());

        return ApiResponse::created(
            data: new AuthResource($authData),
            message: 'Account created successfully'
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $authData = $this->authService->login(
            data: $request->validated(),
            ipAddress: $request->ip()
        );

        return ApiResponse::success(
            data: new AuthResource($authData),
            message: 'Logged in successfully'
        );
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: new UserResource($request->user()),
            message: 'Authenticated user retrieved successfully'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return ApiResponse::success(
            message: 'Logged out successfully'
        );
    }
}
