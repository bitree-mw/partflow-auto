<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class AlertsController extends Controller
{
    public function index(): View
    {
        return view('alerts.index', [
            'title' => 'Alerts',
            'description' => 'Review operational warnings before they become sales or stock problems.',
            'alerts' => [
                ['type' => 'Low stock', 'item' => 'Toyota Corolla Brake Pads Front', 'branch' => 'Old Town', 'detail' => '1 available, recommended 12', 'priority' => 'High', 'priority_tone' => 'danger'],
                ['type' => 'Out of stock', 'item' => 'Nissan Tiida Oil Filter', 'branch' => 'Mzuzu', 'detail' => '0 available across current branch', 'priority' => 'High', 'priority_tone' => 'danger'],
                ['type' => 'Margin warning', 'item' => 'Mazda Demio Rear Shock Absorber', 'branch' => 'Area 23', 'detail' => 'Recent discount pushed margin below policy', 'priority' => 'Medium', 'priority_tone' => 'warning'],
                ['type' => 'Transfer pending', 'item' => 'Honda Fit Fuel Pump', 'branch' => 'City Centre', 'detail' => 'Transfer request awaiting approval', 'priority' => 'Medium', 'priority_tone' => 'warning'],
            ],
        ]);
    }
}
