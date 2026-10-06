<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportExportRequest;
use App\Models\Site;
use App\Services\PackageService;
use App\Services\ReportExportService;
use App\Services\ReportService;
use App\Services\SiteAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function __construct(
        private readonly SiteAccessService $siteAccessService,
        private readonly ReportExportService $reportExportService,
        private readonly ReportService $reportService,
        private readonly PackageService $packages
    ) {}

    public function index(ReportExportRequest $request): View
    {
        $validated = $request->validated();
        $selectedSiteId = $this->selectedSiteId($request, $validated);

        if (filled($selectedSiteId)) {
            $this->siteAccessService->authorizeSite($request->user(), (int) $selectedSiteId);
        }

        $selectedBranchName = filled($selectedSiteId)
            ? Site::query()->whereKey((int) $selectedSiteId)->value('name')
            : null;

        return view('reports.index', [
            'title' => 'Reports',
            'description' => 'View or download sales, purchasing, inventory, creditor, and debtor records for the selected period and branch.',
            'dateFrom' => today()->startOfMonth()->toDateString(),
            'dateTo' => today()->toDateString(),
            'branchOptions' => $this->branchOptions($request),
            'selectedSiteId' => $selectedSiteId,
            'selectedBranchName' => $selectedBranchName ?? 'All branches',
            'reportCards' => array_values(array_filter([
                ['name' => 'Sales report', 'type' => 'sales', 'detail' => 'Every sale and line item, including price, discount, VAT, payment status, balance, and profit.', 'status' => 'Transactions'],
                ['name' => 'Purchase report', 'type' => 'purchases', 'detail' => 'Every supplier purchase and line item, including quantities, costs, totals, payments, and balances.', 'status' => 'Transactions'],
                ['name' => 'Inventory report', 'type' => 'inventory-valuation', 'detail' => 'Current stock by part and branch with purchase cost, selling price, cost value, expected sales value, and potential margin.', 'status' => 'Current position'],
                ['name' => 'Creditors report', 'type' => 'creditor-balances', 'detail' => 'Every supplier purchase with an outstanding balance in the selected period.', 'status' => 'Outstanding purchases'],
                ['name' => 'Debtors report', 'type' => 'debtor-balances', 'detail' => 'Every customer sale with an outstanding balance in the selected period.', 'status' => 'Outstanding sales'],
            ], fn (array $card): bool => $this->packages->reportAllowed($card['type']))),
        ]);
    }

    public function viewReport(ReportExportRequest $request): View
    {
        $validated = $request->validated();
        $selectedSiteId = $this->selectedSiteId($request, $validated);

        if (filled($selectedSiteId)) {
            $this->siteAccessService->authorizeSite($request->user(), (int) $selectedSiteId);
        }

        $reportType = (string) ($validated['report_type'] ?? 'sales');
        $perPage = (int) ($validated['per_page'] ?? 25);
        $page = (int) ($validated['page'] ?? 1);
        $filters = array_filter([
            'date_from' => $validated['date_from'] ?? today()->startOfMonth()->toDateString(),
            'date_to' => $validated['date_to'] ?? today()->toDateString(),
            'site_id' => $selectedSiteId,
            'status' => $validated['status'] ?? null,
            'product_id' => $validated['product_id'] ?? null,
            'contact_id' => $validated['contact_id'] ?? null,
            'payment_account_id' => $validated['payment_account_id'] ?? null,
            'expense_category_id' => $validated['expense_category_id'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
        $report = $this->reportService->paginated(
            $reportType,
            $this->siteAccessService->scopeFilters($request->user(), $filters),
            $perPage,
            $page
        );
        $report['rows']
            ->withPath(route('web.reports.view'))
            ->appends($request->except('page'));

        return view('reports.view', [
            'title' => $report['title'],
            'description' => 'Review report records in the system, ordered from newest to oldest where a transaction date is available.',
            'report' => $report,
            'reportFilters' => $filters,
            'selectedBranchName' => filled($selectedSiteId)
                ? Site::query()->whereKey((int) $selectedSiteId)->value('name')
                : 'All branches',
            'perPage' => $perPage,
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

    private function selectedSiteId(ReportExportRequest $request, array $validated): mixed
    {
        return $request->has('site_id')
            ? ($validated['site_id'] ?? null)
            : $request->session()->get('pos_site_id');
    }
}
