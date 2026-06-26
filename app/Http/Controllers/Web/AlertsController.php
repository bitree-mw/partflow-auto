<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AlertService;
use Illuminate\Contracts\View\View;

class AlertsController extends Controller
{
    public function __construct(
        private readonly AlertService $alertService
    ) {}

    public function index(): View
    {
        return view('alerts.index', [
            'title' => 'Alerts',
            'description' => 'Review operational warnings before they become sales or stock problems.',
            'alerts' => $this->alertService->all(),
        ]);
    }
}
