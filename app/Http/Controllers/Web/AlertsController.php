<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AlertService;
use App\Services\SiteAccessService;
use App\Support\CollectionPaginator;
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
        $filters = [];
        $siteId = (int) $request->query('site_id', $request->session()->get('pos_site_id', 0));

        if ($siteId > 0) {
            $filters['site_id'] = $siteId;
        }

        return view('alerts.index', [
            'title' => 'Alerts',
            'description' => 'Review operational warnings before they become sales or stock problems.',
            'alerts' => CollectionPaginator::paginate(
                $this->alertService->all($this->siteAccessService->scopeFilters($request->user(), $filters)),
                $request
            ),
        ]);
    }
}
