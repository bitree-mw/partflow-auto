<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Site\StoreSiteRequest;
use App\Http\Requests\Site\UpdateSiteRequest;
use App\Http\Resources\SiteResource;
use App\Models\Site;
use App\Services\SiteService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function __construct(
        private readonly SiteService $siteService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $sites = $this->siteService->list($request->query());

        return ApiResponse::success(
            data: SiteResource::collection($sites),
            message: 'Sites retrieved successfully'
        );
    }

    public function store(StoreSiteRequest $request): JsonResponse
    {
        $site = $this->siteService->create($request->validated());

        return ApiResponse::created(
            data: new SiteResource($site),
            message: 'Site created successfully'
        );
    }

    public function show(Site $site): JsonResponse
    {
        return ApiResponse::success(
            data: new SiteResource($site),
            message: 'Site retrieved successfully'
        );
    }

    public function update(UpdateSiteRequest $request, Site $site): JsonResponse
    {
        $site = $this->siteService->update($site, $request->validated());

        return ApiResponse::updated(
            data: new SiteResource($site),
            message: 'Site updated successfully'
        );
    }

    public function destroy(Site $site): JsonResponse
    {
        $this->siteService->delete($site);

        return ApiResponse::deleted('Site deleted successfully');
    }
}
