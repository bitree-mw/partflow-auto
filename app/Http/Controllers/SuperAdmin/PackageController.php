<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdatePackageRequest;
use App\Http\Requests\SuperAdmin\UpdateSupportContactRequest;
use App\Services\PackageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PackageController extends Controller
{
    public function __construct(private readonly PackageService $packages) {}

    public function edit(): View
    {
        return view('suadmin.package', [
            'title' => 'Package',
            'packages' => $this->packages->packages(),
            'currentKey' => $this->packages->currentKey(),
            'supportContact' => $this->packages->supportContact(),
        ]);
    }

    public function update(UpdatePackageRequest $request): RedirectResponse
    {
        try {
            $this->packages->change($request->validated('package'));
        } catch (BusinessRuleException $exception) {
            return redirect()->route('suadmin.package.edit')->with('error', $exception->getMessage());
        }

        return redirect()->route('suadmin.package.edit')
            ->with('success', "This installation is now on the {$this->packages->current()['name']} package.");
    }

    public function updateSupport(UpdateSupportContactRequest $request): RedirectResponse
    {
        $this->packages->updateSupportContact($request->validated());

        return redirect()->route('suadmin.package.edit')->with('success', '24/7 support contact details saved.');
    }
}
