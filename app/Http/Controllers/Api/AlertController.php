<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AlertService;
use App\Services\SiteAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function __construct(
        private readonly AlertService $alertService,
        private readonly SiteAccessService $siteAccessService
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->alertService->all($this->siteAccessService->scopeFilters($request->user(), $request->query())),
            'Alerts retrieved successfully'
        );
    }

    public function summary(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->alertService->summary($this->siteAccessService->scopeFilters($request->user(), $request->query())),
            'Alert summary retrieved successfully'
        );
    }
}
