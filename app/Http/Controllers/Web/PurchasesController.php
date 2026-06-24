<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PurchasesController extends Controller
{
    public function index(): View
    {
        return view('purchases.index', [
            'title' => 'Purchases',
            'description' => 'Receive parts from suppliers, track payable balances, and monitor stock that has entered each branch.',
            'summary' => [
                ['label' => 'Purchase value', 'value' => 'MWK 3.84M', 'detail' => 'This month', 'tone' => 'neutral'],
                ['label' => 'Supplier payable', 'value' => 'MWK 970K', 'detail' => 'Partial and unpaid purchases', 'tone' => 'warning'],
                ['label' => 'Received items', 'value' => '184', 'detail' => 'Across active branches', 'tone' => 'success'],
                ['label' => 'Return issues', 'value' => 'MWK 142K', 'detail' => 'Pending supplier action', 'tone' => 'danger'],
            ],
            'purchases' => [
                ['number' => 'PUR-0318', 'date' => '2026-06-22', 'supplier' => 'Japan Auto Imports', 'site' => 'Kanengo Warehouse', 'items' => 14, 'total' => 'MWK 1,240,000', 'paid' => 'MWK 700,000', 'status' => 'Partial', 'tone' => 'warning'],
                ['number' => 'PUR-0319', 'date' => '2026-06-21', 'supplier' => 'SA Parts Depot', 'site' => 'Old Town', 'items' => 9, 'total' => 'MWK 680,000', 'paid' => 'MWK 680,000', 'status' => 'Paid', 'tone' => 'success'],
                ['number' => 'PUR-0320', 'date' => '2026-06-20', 'supplier' => 'Local Consumables', 'site' => 'Area 23', 'items' => 22, 'total' => 'MWK 410,000', 'paid' => 'MWK 0', 'status' => 'Unpaid', 'tone' => 'danger'],
                ['number' => 'PUR-0321', 'date' => '2026-06-18', 'supplier' => 'Japan Auto Imports', 'site' => 'Mzuzu', 'items' => 7, 'total' => 'MWK 925,000', 'paid' => 'MWK 925,000', 'status' => 'Paid', 'tone' => 'success'],
            ],
        ]);
    }

    public function create(): View
    {
        return view('purchases.create', [
            'title' => 'Add Purchase',
            'description' => 'Record parts bought from a supplier and prepare the stock receiving details for API connection.',
            'suppliers' => ['Japan Auto Imports', 'SA Parts Depot', 'Local Consumables'],
            'sites' => ['Area 23', 'Old Town', 'City Centre', 'Mzuzu', 'Kanengo Warehouse'],
            'parts' => [
                'Toyota Corolla Brake Pads Front',
                'Nissan Tiida Oil Filter',
                'Mazda Demio Rear Shock Absorber',
                'Honda Fit Fuel Pump',
                'Ford Ranger Air Filter',
            ],
            'paymentStatuses' => ['Unpaid', 'Partial', 'Paid'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('web.purchases.index')->with('success', 'Purchase entry captured in the UI. API connection will be added later.');
    }
}
