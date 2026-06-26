<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\SystemConfigurationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function __construct(
        private readonly SystemConfigurationService $systemConfiguration
    ) {}

    public function index(): View
    {
        return view('settings.index', [
            'title' => 'Application Settings',
            'description' => 'Configure company identity, sites, users, document numbering, and stock operation defaults.',
            'settings' => $this->systemConfiguration->settings(),
            'sites' => $this->systemConfiguration->sites(),
            'countries' => config('countries'),
            'currencies' => ['MWK', 'USD', 'ZAR', 'EUR', 'GBP', 'JPY', 'CNY', 'AED'],
            'siteTypes' => ['Shop', 'Branch', 'Warehouse'],
            'costingMethods' => ['Last purchase cost', 'Weighted average cost', 'Manual standard cost'],
            'documentSeries' => $this->systemConfiguration->documentSeries(),
            'roles' => $this->systemConfiguration->roles(),
            'users' => $this->systemConfiguration->users(),
            'settingGroups' => $this->systemConfiguration->settingGroups(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        return back()->with('success', 'Settings reviewed. Persistent system settings storage is ready for the next pass.');
    }
}
