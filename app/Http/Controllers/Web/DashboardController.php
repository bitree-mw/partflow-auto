<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\DashboardRequest;
use App\Services\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    public function index(DashboardRequest $request): View
    {
        $filters = $this->filters($request);

        return view('dashboard.index', [
            'title' => now()->format('F j, Y'),
            'description' => null,
            'greetingName' => auth()->user()?->name ?? 'System',
            ...$this->dashboardService->overview($filters, $request->user()),
        ]);
    }

    public function live(DashboardRequest $request): JsonResponse
    {
        return response()->json(
            $this->dashboardService->todaySnapshot($this->filters($request), $request->user())
        );
    }

    private function filters(DashboardRequest $request): array
    {
        $filters = $request->validated();

        if (! array_key_exists('site_id', $filters)) {
            $sessionSiteId = (int) $request->session()->get('pos_site_id', 0);

            if ($sessionSiteId > 0) {
                $filters['site_id'] = $sessionSiteId;
            }
        }

        return $filters;
    }
}
