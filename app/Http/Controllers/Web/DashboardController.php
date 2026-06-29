<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only('site_id');

        return view('dashboard.index', [
            'title' => now()->format('F j, Y'),
            'description' => null,
            'greetingName' => auth()->user()?->name ?? 'System',
            ...$this->dashboardService->overview($filters),
        ]);
    }
}
