<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SiteResource;
use App\Models\Site;
use App\Services\SiteAccessService;
use App\Services\SiteService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Read-only: sites are created and deleted from the super admin console, and edited or (de)activated from the web back office.
class SiteController extends Controller
{
    public function __construct(
        private readonly SiteService $siteService,
        private readonly SiteAccessService $siteAccessService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $sites = $this->siteService->list($request->query(), $request->user());

        return ApiResponse::success(
            data: SiteResource::collection($sites),
            message: 'Sites retrieved successfully'
        );
    }

    public function show(Request $request, Site $site): JsonResponse
    {
        $this->siteAccessService->authorizeSite($request->user(), $site->id);

        return ApiResponse::success(
            data: new SiteResource($site),
            message: 'Site retrieved successfully'
        );
    }
}
