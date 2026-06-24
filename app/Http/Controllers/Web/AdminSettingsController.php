<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function index(): View
    {
        // These values are UI fixtures until the settings screens are wired to the API/services.
        return view('settings.index', [
            'title' => 'Application Settings',
            'description' => 'Configure company identity, sites, users, document numbering, and stock operation defaults.',
            'settings' => [
                'business_name' => 'PartFlow Auto',
                'legal_name' => 'PartFlow Auto Limited',
                'registration_number' => 'MW-BR-1042',
                'base_country' => 'Malawi',
                'base_currency' => 'MWK',
                'default_branch' => 'Area 23',
                'stock_costing_method' => 'Last purchase cost',
                'low_stock_policy' => 'Use product default unless branch override exists',
            ],
            'sites' => [
                ['name' => 'Area 23', 'type' => 'Shop', 'city' => 'Lilongwe', 'country' => 'Malawi', 'status' => 'Active'],
                ['name' => 'Old Town', 'type' => 'Branch', 'city' => 'Lilongwe', 'country' => 'Malawi', 'status' => 'Active'],
                ['name' => 'City Centre', 'type' => 'Branch', 'city' => 'Lilongwe', 'country' => 'Malawi', 'status' => 'Active'],
                ['name' => 'Mzuzu', 'type' => 'Branch', 'city' => 'Mzuzu', 'country' => 'Malawi', 'status' => 'Active'],
            ],
            'countries' => config('countries'),
            'currencies' => ['MWK', 'USD', 'ZAR', 'EUR', 'GBP', 'JPY', 'CNY', 'AED'],
            'siteTypes' => ['Shop', 'Branch', 'Warehouse'],
            'costingMethods' => ['Last purchase cost', 'Weighted average cost', 'Manual standard cost'],
            'documentSeries' => [
                ['document' => 'POS Sales', 'prefix' => 'POS', 'next_number' => '1046'],
                ['document' => 'Purchases', 'prefix' => 'PUR', 'next_number' => '318'],
                ['document' => 'Transfers', 'prefix' => 'TRF', 'next_number' => '77'],
                ['document' => 'Stock Takes', 'prefix' => 'STK', 'next_number' => '24'],
            ],
            'roles' => ['System Administrator', 'Branch Manager', 'Cashier', 'Stock Controller', 'Reports Viewer'],
            'users' => [
                ['name' => 'System Admin', 'email' => 'admin@partflow.test', 'role' => 'System Administrator', 'site' => 'All sites', 'status' => 'Active'],
                ['name' => 'Area 23 Cashier', 'email' => 'cashier.area23@partflow.test', 'role' => 'Cashier', 'site' => 'Area 23', 'status' => 'Active'],
                ['name' => 'Mzuzu Stock Lead', 'email' => 'stock.mzuzu@partflow.test', 'role' => 'Stock Controller', 'site' => 'Mzuzu', 'status' => 'Pending'],
            ],
            'settingGroups' => [
                ['name' => 'Branches', 'items' => ['Area 23', 'Old Town', 'City Centre', 'Mzuzu', 'Kanengo Warehouse']],
                ['name' => 'Catalogue setup', 'items' => ['Car models', 'Fuel types', 'Part types', 'Parts']],
                ['name' => 'Operations', 'items' => ['POS sales', 'Purchases', 'Stock receiving', 'Transfers']],
                ['name' => 'Permissions', 'items' => ['Sales', 'Purchases', 'Reports', 'Adjustments']],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        // Keep the form workflow usable without persisting anything while the UI is being shaped.
        return back()->with('success', 'Settings UI saved locally for now. API connection will be added later.');
    }
}
