<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class ReportsController extends Controller
{
    public function index(): View
    {
        return view('reports.index', [
            'title' => 'Reports',
            'description' => 'Operational reports for sales, stock, profit, transfers, and branch performance.',
            'reportCards' => [
                ['name' => 'Most sold parts', 'detail' => 'Rank parts by units sold and revenue.', 'status' => 'Ready'],
                ['name' => 'Profit by product', 'detail' => 'Compare selling price, cost, and gross margin.', 'status' => 'Ready'],
                ['name' => 'Current stock by branch', 'detail' => 'View available quantity across every site.', 'status' => 'Ready'],
                ['name' => 'Stock take variance', 'detail' => 'Find shrinkage and count differences.', 'status' => 'Draft'],
                ['name' => 'Credit outstanding', 'detail' => 'Review unpaid or partially paid sales.', 'status' => 'Draft'],
                ['name' => 'Transfer history', 'detail' => 'Trace branch-to-branch stock movement.', 'status' => 'Ready'],
            ],
        ]);
    }
}
