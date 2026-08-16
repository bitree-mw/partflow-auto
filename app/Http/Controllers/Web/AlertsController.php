<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AlertService;
use App\Services\SiteAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AlertsController extends Controller
{
    public function __construct(
        private readonly AlertService $alertService,
        private readonly SiteAccessService $siteAccessService
    ) {}

    public function index(Request $request): View
    {
        return view('alerts.index', [
            'title' => 'Alerts',
            'description' => 'Review operational warnings before they become sales or stock problems.',
            'alerts' => $this->alertService->all($this->siteAccessService->scopeFilters($request->user())),
        ]);
    }
}
