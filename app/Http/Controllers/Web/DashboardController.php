<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\DashboardRequest;
use App\Services\DashboardService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    public function index(DashboardRequest $request): View
    {
        $filters = $request->validated();

        return view('dashboard.index', [
            'title' => now()->format('F j, Y'),
            'description' => null,
            'greetingName' => auth()->user()?->name ?? 'System',
            ...$this->dashboardService->overview($filters, $request->user()),
        ]);
    }
}
