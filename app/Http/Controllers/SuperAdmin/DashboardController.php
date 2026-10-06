<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdminOverviewService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(SuperAdminOverviewService $overview): View
    {
        return view('suadmin.dashboard', [
            'title' => 'Overview',
            ...$overview->summary(),
        ]);
    }
}
