<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\StoreSiteRequest;
use App\Http\Requests\Site\UpdateSiteRequest;
use App\Http\Requests\Site\UpdateSiteStatusRequest;
use App\Models\Site;
use App\Services\SiteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    private const SITE_TYPES = ['shop' => 'Shop', 'branch' => 'Branch', 'warehouse' => 'Warehouse'];

    public function __construct(private readonly SiteService $siteService) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'type', 'status']);

        return view('suadmin.sites.index', [
            'title' => 'Sites and locations',
            'sites' => $this->siteService->paginateForAdministration($filters),
            'filters' => $filters,
            'siteTypes' => self::SITE_TYPES,
        ]);
    }

    public function create(): View
    {
        return view('suadmin.sites.form', [
            'title' => 'Add site',
            'site' => new Site(['type' => 'branch']),
            'siteTypes' => self::SITE_TYPES,
        ]);
    }

    public function store(StoreSiteRequest $request): RedirectResponse
    {
        try {
            $site = $this->siteService->create($request->validated());
        } catch (BusinessRuleException $exception) {
            return redirect()->route('suadmin.sites.create')->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('suadmin.sites.index')->with('success', "Site {$site->name} ({$site->code}) created.");
    }

    public function edit(Site $site): View
    {
        return view('suadmin.sites.form', [
            'title' => "Edit {$site->name}",
            'site' => $site,
            'siteTypes' => self::SITE_TYPES,
        ]);
    }

    public function update(UpdateSiteRequest $request, Site $site): RedirectResponse
    {
        $this->siteService->update($site, $request->validated());

        return redirect()->route('suadmin.sites.index')->with('success', 'Site updated.');
    }

    public function status(UpdateSiteStatusRequest $request, Site $site): RedirectResponse
    {
        $active = $request->boolean('is_active');

        try {
            $this->siteService->setActive($site, $active);
        } catch (BusinessRuleException $exception) {
            return redirect()->route('suadmin.sites.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('suadmin.sites.index')->with('success', $active ? "{$site->name} activated." : "{$site->name} deactivated.");
    }

    public function destroy(Site $site): RedirectResponse
    {
        try {
            $this->siteService->delete($site);
        } catch (BusinessRuleException $exception) {
            return redirect()->route('suadmin.sites.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('suadmin.sites.index')->with('success', "{$site->name} deleted. Its past transactions keep their history.");
    }
}
