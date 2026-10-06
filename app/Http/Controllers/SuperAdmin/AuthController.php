<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SuperAdminLoginRequest;
use App\Services\SuperAdminAuthService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly SuperAdminAuthService $superAdminAuth) {}

    public function create(Request $request): View|RedirectResponse
    {
        if ($this->superAdminAuth->check($request->session())) {
            return redirect()->route('suadmin.dashboard');
        }

        return view('suadmin.login', [
            'title' => 'Super admin',
            'configured' => $this->superAdminAuth->isConfigured(),
        ]);
    }

    public function store(SuperAdminLoginRequest $request): RedirectResponse
    {
        if (! $this->superAdminAuth->attempt($request->validated('password'), $request->session())) {
            return redirect()->route('suadmin.login')->withErrors(['password' => 'The super admin password is incorrect.']);
        }

        return redirect()->route('suadmin.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->superAdminAuth->logout($request->session());

        return redirect()->route('suadmin.login')->with('success', 'Signed out of the super admin console.');
    }
}
