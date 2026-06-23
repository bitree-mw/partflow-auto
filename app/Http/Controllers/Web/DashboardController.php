<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // Mock dashboard data mirrors the existing report and dashboard API contracts.
        return view('dashboard.index', [
            'title' => 'June 23, 2026',
            'description' => null,
            'metrics' => [
                ['label' => 'Today sales', 'value' => 'MWK 2.84M', 'change' => '+18.4%', 'tone' => 'good', 'trend' => 'positive'],
                ['label' => 'Today profit', 'value' => 'MWK 684K', 'change' => '24.1% margin', 'tone' => 'good', 'trend' => 'positive'],
                ['label' => 'Stock value', 'value' => 'MWK 86.2M', 'change' => '4 branches', 'tone' => 'neutral', 'trend' => 'neutral'],
                ['label' => 'Loss exposure', 'value' => 'MWK 312K', 'change' => '-6.8% margin risk', 'tone' => 'risk', 'trend' => 'negative'],
            ],
            'currentSales' => [
                ['invoice' => 'POS-1042', 'branch' => 'Area 23', 'customer' => 'Walk-in', 'amount' => 'MWK 17,000', 'profit' => 'MWK 5,120', 'status' => 'Paid', 'payment_tone' => 'success'],
                ['invoice' => 'POS-1043', 'branch' => 'Old Town', 'customer' => 'AutoFix Garage', 'amount' => 'MWK 186,500', 'profit' => 'MWK 41,300', 'status' => 'Partial', 'payment_tone' => 'warning'],
                ['invoice' => 'POS-1044', 'branch' => 'City Centre', 'customer' => 'Walk-in', 'amount' => 'MWK 62,000', 'profit' => 'MWK 13,900', 'status' => 'Paid', 'payment_tone' => 'success'],
            ],
            'mostSoldParts' => [
                ['part' => 'Brake Pads Front', 'code' => 'TYCO14BP-I', 'units' => 42, 'sales' => 'MWK 1.26M', 'profit' => 'MWK 318K'],
                ['part' => 'Oil Filter', 'code' => 'NSNT12OF-I', 'units' => 39, 'sales' => 'MWK 468K', 'profit' => 'MWK 126K'],
                ['part' => 'Spark Plug Set', 'code' => 'TYCO10SP-I', 'units' => 28, 'sales' => 'MWK 392K', 'profit' => 'MWK 101K'],
                ['part' => 'Shock Absorber Rear', 'code' => 'MZDM12SA-I', 'units' => 11, 'sales' => 'MWK 913K', 'profit' => 'MWK 206K'],
            ],
            'branchPerformance' => [
                ['branch' => 'Area 23', 'sales' => 'MWK 1.12M', 'profit' => 'MWK 276K', 'margin' => '24.6%', 'margin_tone' => 'positive', 'stockouts' => 3],
                ['branch' => 'Old Town', 'sales' => 'MWK 964K', 'profit' => 'MWK 229K', 'margin' => '23.8%', 'margin_tone' => 'positive', 'stockouts' => 7],
                ['branch' => 'City Centre', 'sales' => 'MWK 511K', 'profit' => 'MWK 122K', 'margin' => '23.9%', 'margin_tone' => 'positive', 'stockouts' => 2],
                ['branch' => 'Mzuzu', 'sales' => 'MWK 247K', 'profit' => 'MWK -8K', 'margin' => '-3.4%', 'margin_tone' => 'negative', 'stockouts' => 11],
            ],
            'lossRisks' => [
                ['label' => 'Purchase returns', 'value' => 'MWK 142K', 'detail' => '3 supplier return documents awaiting approval'],
                ['label' => 'Stock take variance', 'value' => 'MWK 96K', 'detail' => 'Brake and filter variance concentrated at Mzuzu'],
                ['label' => 'Discount leakage', 'value' => 'MWK 74K', 'detail' => 'Invoice discounts above policy in 6 sales'],
            ],
            'stockAlerts' => [
                ['part' => 'Toyota Corolla Brake Pads', 'branch' => 'Old Town', 'available' => 1, 'recommended' => 12, 'priority_tone' => 'warning'],
                ['part' => 'Nissan Tiida Oil Filter', 'branch' => 'Mzuzu', 'available' => 0, 'recommended' => 10, 'priority_tone' => 'danger'],
                ['part' => 'Mazda Demio Rear Shock', 'branch' => 'Area 23', 'available' => 2, 'recommended' => 6, 'priority_tone' => 'warning'],
            ],
        ]);
    }
}
