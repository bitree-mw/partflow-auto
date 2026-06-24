<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactDirectoryController extends Controller
{
    public function customers(): View
    {
        return view('contacts.index', [
            'title' => 'Customers',
            'description' => 'Manage customers, credit sales, balances, and buying performance.',
            'mode' => 'customers',
            'createRoute' => route('web.customers.create'),
            'contacts' => [
                ['code' => 'CUS-001', 'name' => 'AutoFix Garage', 'phone' => '+265 991 200 111', 'email' => 'orders@autofix.test', 'credit_limit' => 'MWK 750,000', 'balance' => 'MWK 186,500', 'performance' => '12 sales - 82% paid'],
                ['code' => 'CUS-002', 'name' => 'Northern Motors', 'phone' => '+265 888 455 221', 'email' => 'parts@northern.test', 'credit_limit' => 'MWK 500,000', 'balance' => 'MWK 145,000', 'performance' => '7 sales - 71% paid'],
                ['code' => 'CUS-003', 'name' => 'Walk-in Customers', 'phone' => 'N/A', 'email' => 'N/A', 'credit_limit' => 'MWK 0', 'balance' => 'MWK 0', 'performance' => '118 cash sales'],
            ],
            'analytics' => [
                ['label' => 'Credit outstanding', 'value' => 'MWK 331,500', 'detail' => 'Across active customer accounts', 'tone' => 'warning'],
                ['label' => 'Credit sales', 'value' => 'MWK 1.42M', 'detail' => 'Selected period', 'tone' => 'neutral'],
                ['label' => 'Collections', 'value' => 'MWK 982K', 'detail' => '69% collected', 'tone' => 'success'],
                ['label' => 'Overdue accounts', 'value' => '2', 'detail' => 'Require follow-up', 'tone' => 'danger'],
            ],
        ]);
    }

    public function suppliers(): View
    {
        return view('contacts.index', [
            'title' => 'Suppliers',
            'description' => 'Manage suppliers, purchase activity, payable balances, and supply performance.',
            'mode' => 'suppliers',
            'createRoute' => route('web.suppliers.create'),
            'contacts' => [
                ['code' => 'SUP-001', 'name' => 'Japan Auto Imports', 'phone' => '+265 999 800 441', 'email' => 'supply@japanimports.test', 'credit_limit' => 'MWK 2.5M', 'balance' => 'MWK 690,000', 'performance' => '9 purchases - 4.2 day lead'],
                ['code' => 'SUP-002', 'name' => 'SA Parts Depot', 'phone' => '+27 11 555 9000', 'email' => 'orders@saparts.test', 'credit_limit' => 'MWK 1.8M', 'balance' => 'MWK 280,000', 'performance' => '6 purchases - 6.1 day lead'],
                ['code' => 'SUP-003', 'name' => 'Local Consumables', 'phone' => '+265 882 401 771', 'email' => 'sales@localconsumables.test', 'credit_limit' => 'MWK 400,000', 'balance' => 'MWK 0', 'performance' => '14 purchases - paid'],
            ],
            'analytics' => [
                ['label' => 'Purchases', 'value' => 'MWK 3.84M', 'detail' => 'Selected period', 'tone' => 'neutral'],
                ['label' => 'Supplier payable', 'value' => 'MWK 970K', 'detail' => 'Outstanding purchases', 'tone' => 'warning'],
                ['label' => 'Returns pending', 'value' => 'MWK 142K', 'detail' => 'Awaiting supplier approval', 'tone' => 'danger'],
                ['label' => 'Avg lead time', 'value' => '5.3 days', 'detail' => 'Across active suppliers', 'tone' => 'success'],
            ],
        ]);
    }

    public function createCustomer(): View
    {
        return $this->contactForm('customers', 'Add Customer', 'Create a customer account for cash or credit sales.', route('web.customers.store'));
    }

    public function createSupplier(): View
    {
        return $this->contactForm('suppliers', 'Add Supplier', 'Create a supplier account for purchases and payable tracking.', route('web.suppliers.store'));
    }

    public function storeCustomer(Request $request): RedirectResponse
    {
        return redirect()->route('web.customers.index')->with('success', 'Customer setup captured in the UI. API connection will be added later.');
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        return redirect()->route('web.suppliers.index')->with('success', 'Supplier setup captured in the UI. API connection will be added later.');
    }

    private function contactForm(string $mode, string $title, string $description, string $action): View
    {
        return view('contacts.create', compact('mode', 'title', 'description', 'action'));
    }
}
