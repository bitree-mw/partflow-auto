<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\PreviewDataCleanupRequest;
use App\Http\Requests\SuperAdmin\RunDataCleanupRequest;
use App\Services\DataCleanupService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class DataCleanupController extends Controller
{
    public function __construct(private readonly DataCleanupService $cleanup) {}

    public function index(): View
    {
        return $this->page();
    }

    public function preview(PreviewDataCleanupRequest $request): View
    {
        $selection = $request->selection();

        return $this->page($selection, $this->cleanup->preview($selection));
    }

    public function destroy(RunDataCleanupRequest $request): RedirectResponse
    {
        $summary = $this->cleanup->run($request->selection());

        return redirect()->route('suadmin.data-cleanup.index')
            ->with('success', 'Data cleanup finished. '.number_format(array_sum(array_column($summary['categories'], 'deleted'))).' record(s) were permanently deleted.')
            ->with('cleanupSummary', $summary);
    }

    private function page(?array $selection = null, ?array $preview = null): View
    {
        return view('suadmin.data-cleanup', [
            'title' => 'Data cleanup',
            'categories' => DataCleanupService::CATEGORIES,
            'selection' => $selection,
            'preview' => $preview,
            'fullStockWipe' => $selection ? $this->cleanup->isFullStockWipe($selection) : false,
        ]);
    }
}
