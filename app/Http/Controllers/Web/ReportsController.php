<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportExportRequest;
use App\Models\Site;
use App\Services\ReportExportService;
use App\Services\SiteAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function __construct(
        private readonly SiteAccessService $siteAccessService,
        private readonly ReportExportService $reportExportService
    ) {}

    public function index(Request $request): View
    {
        $selectedSiteId = $request->query('site_id');

        if (filled($selectedSiteId)) {
            $this->siteAccessService->authorizeSite($request->user(), (int) $selectedSiteId);
        }

        $selectedBranchName = filled($selectedSiteId)
            ? Site::query()->whereKey((int) $selectedSiteId)->value('name')
            : null;

        return view('reports.index', [
            'title' => 'Reports',
            'description' => 'Download complete sales, purchasing, inventory, creditor, and debtor records for the selected period and branch.',
            'dateFrom' => today()->startOfMonth()->toDateString(),
            'dateTo' => today()->toDateString(),
            'branchOptions' => $this->branchOptions($request),
            'selectedSiteId' => $selectedSiteId,
            'selectedBranchName' => $selectedBranchName ?? 'All branches',
            'reportCards' => [
                ['name' => 'Sales report', 'type' => 'sales', 'detail' => 'Every sale and line item, including price, discount, VAT, payment status, balance, and profit.', 'status' => 'Transactions'],
                ['name' => 'Purchase report', 'type' => 'purchases', 'detail' => 'Every supplier purchase and line item, including quantities, costs, totals, payments, and balances.', 'status' => 'Transactions'],
                ['name' => 'Inventory report', 'type' => 'inventory-valuation', 'detail' => 'Current stock by part and branch with purchase cost, selling price, cost value, expected sales value, and potential margin.', 'status' => 'Current position'],
                ['name' => 'Creditors report', 'type' => 'creditor-balances', 'detail' => 'Every supplier purchase with an outstanding balance in the selected period.', 'status' => 'Outstanding purchases'],
                ['name' => 'Debtors report', 'type' => 'debtor-balances', 'detail' => 'Every customer sale with an outstanding balance in the selected period.', 'status' => 'Outstanding sales'],
            ],
        ]);
    }

    public function export(ReportExportRequest $request): StreamedResponse
    {
        $validated = $request->validated();
        $reportType = (string) ($validated['report_type'] ?? 'sales');
        unset($validated['report_type']);

        $filters = $this->siteAccessService->scopeFilters(
            $request->user(),
            array_filter($validated, fn ($value) => $value !== null && $value !== '')
        );

        return $this->reportExportService->download($reportType, $filters);
    }

    private function branchOptions(Request $request): array
    {
        $siteIds = $this->siteAccessService->allowedSiteIds($request->user());

        return Site::query()
            ->active()
            ->whereIn('id', $siteIds)
            ->orderBy('name')
            ->get()
            ->map(fn (Site $site): array => [
                'id' => $site->id,
                'name' => $site->name,
            ])
            ->all();
    }
}
