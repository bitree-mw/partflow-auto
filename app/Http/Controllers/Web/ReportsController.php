<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function index(Request $request): View
    {
        $selectedSiteId = $request->query('site_id');
        $selectedBranchName = filled($selectedSiteId)
            ? Site::query()->whereKey((int) $selectedSiteId)->value('name')
            : null;

        return view('reports.index', [
            'title' => 'Reports',
            'description' => 'Filter dates first, then download full transaction reports for sales, stock, payments, and profit and loss.',
            'dateFrom' => '2026-06-01',
            'dateTo' => '2026-06-24',
            'branchOptions' => $this->branchOptions(),
            'selectedSiteId' => $selectedSiteId,
            'selectedBranchName' => $selectedBranchName ?? 'All branches',
            'profitLossAccounts' => [
                ['name' => 'Sales Revenue', 'type' => 'Income', 'movement' => 'MWK 2.84M'],
                ['name' => 'Cost of Goods Sold', 'type' => 'Cost', 'movement' => 'MWK 1.91M'],
                ['name' => 'Operating Expenses', 'type' => 'Expense', 'movement' => 'MWK 312K'],
                ['name' => 'Returns and Shrinkage', 'type' => 'Loss', 'movement' => 'MWK 96K'],
            ],
            'reportCards' => [
                ['name' => 'Profit and loss', 'type' => 'profit-and-loss', 'detail' => 'Income, cost, expenses, returns, shrinkage, and net movement by transaction.', 'status' => 'Ready'],
                ['name' => 'Sales transactions', 'type' => 'sales', 'detail' => 'Full invoice line fields for every sale in the selected date range.', 'status' => 'Ready'],
                ['name' => 'Most sold parts', 'type' => 'most-selling-products', 'detail' => 'Exports sale lines so rankings can be rebuilt outside the system.', 'status' => 'Ready'],
                ['name' => 'Profit by product', 'type' => 'profit-by-product', 'detail' => 'Full sales lines with cost, price, tax, discount, and profit fields.', 'status' => 'Ready'],
                ['name' => 'Payments by account', 'type' => 'payments', 'detail' => 'Every payment transaction with account, method, reference, and receiver.', 'status' => 'Ready'],
                ['name' => 'Expenses', 'type' => 'expenses', 'detail' => 'Expense transaction rows with category, account, site, and reference.', 'status' => 'Ready'],
                ['name' => 'Current stock by branch', 'type' => 'current-stock', 'detail' => 'Full stock position across every site and product.', 'status' => 'Ready'],
                ['name' => 'Stock take variance', 'type' => 'stock-take-variance', 'detail' => 'Count differences with product and document fields.', 'status' => 'Ready'],
                ['name' => 'Credit outstanding', 'type' => 'customer-balances', 'detail' => 'Unpaid and partially paid sale documents by customer.', 'status' => 'Ready'],
                ['name' => 'Transfer history', 'type' => 'stock-transfers', 'detail' => 'Branch-to-branch transfer documents and item lines.', 'status' => 'Ready'],
            ],
        ]);
    }

    private function branchOptions(): array
    {
        return Site::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (Site $site): array => [
                'id' => $site->id,
                'name' => $site->name,
            ])
            ->all();
    }
}
