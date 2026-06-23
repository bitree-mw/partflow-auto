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
            'description' => 'Configure operational defaults used by stock, sales, tax, branches, and payments.',
            'settings' => [
                'business_name' => 'PartFlow Auto',
                'legal_name' => 'PartFlow Auto Limited',
                'registration_number' => 'MW-BR-1042',
                'tax_number' => 'VAT-175-PF',
                'base_country' => 'Malawi',
                'base_currency' => 'MWK',
                'default_branch' => 'Area 23',
                'default_tax_profile' => 'VAT Inclusive 17.5%',
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
            'taxProfiles' => ['VAT Inclusive 17.5%', 'VAT Exclusive 17.5%', 'Tax Exempt', 'No VAT'],
            'settingGroups' => [
                ['name' => 'Branches', 'items' => ['Area 23', 'Old Town', 'City Centre', 'Mzuzu']],
                ['name' => 'Tax Profiles', 'items' => ['VAT Inclusive 17.5%', 'VAT Exclusive 17.5%', 'Tax Exempt', 'No VAT']],
                ['name' => 'Payment Methods', 'items' => ['Cash', 'Bank', 'Mobile Money', 'Card']],
                ['name' => 'Permissions', 'items' => ['Sales', 'Stock receiving', 'Transfers', 'Adjustments']],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        // Keep the form workflow usable without persisting anything while the UI is being shaped.
        return back()->with('success', 'Settings UI saved locally for now. API connection will be added later.');
    }
}
