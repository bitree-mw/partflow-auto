<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class SalesController extends Controller
{
    public function index(): View
    {
        return view('sales.index', [
            'title' => 'Sales',
            'description' => 'Review recent invoices, payment status, branch activity, and gross profit.',
            'sales' => [
                ['invoice' => 'POS-1042', 'branch' => 'Area 23', 'customer' => 'Walk-in', 'items' => 2, 'total' => 'MWK 54,000', 'profit' => 'MWK 13,400', 'status' => 'Paid', 'payment_tone' => 'success'],
                ['invoice' => 'POS-1043', 'branch' => 'Old Town', 'customer' => 'AutoFix Garage', 'items' => 5, 'total' => 'MWK 186,500', 'profit' => 'MWK 41,300', 'status' => 'Partial', 'payment_tone' => 'warning'],
                ['invoice' => 'POS-1044', 'branch' => 'City Centre', 'customer' => 'Walk-in', 'items' => 1, 'total' => 'MWK 62,000', 'profit' => 'MWK 13,900', 'status' => 'Paid', 'payment_tone' => 'success'],
                ['invoice' => 'POS-1045', 'branch' => 'Mzuzu', 'customer' => 'Northern Motors', 'items' => 3, 'total' => 'MWK 145,000', 'profit' => 'MWK 33,000', 'status' => 'Credit', 'payment_tone' => 'danger'],
            ],
            'summary' => [
                ['label' => 'Gross sales', 'value' => 'MWK 447,500'],
                ['label' => 'Gross profit', 'value' => 'MWK 101,600'],
                ['label' => 'Credit sales', 'value' => 'MWK 145,000'],
                ['label' => 'Transactions', 'value' => '4'],
            ],
        ]);
    }
}
