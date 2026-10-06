<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreUserAccountRequest;
use App\Http\Requests\SuperAdmin\UpdateUserAccountRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserAccountService $userAccounts) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'role_id', 'status']);

        return view('suadmin.users.index', [
            'title' => 'User and admin accounts',
            'users' => $this->userAccounts->paginate($filters)->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role?->name ?? 'No role',
                'is_administrator' => $this->userAccounts->isAdministrator($user),
                'default_site' => $user->siteAccesses->first()?->site?->name ?? 'All sites',
                'is_active' => (bool) $user->is_active,
                'last_login' => $user->last_login_at?->diffForHumans() ?? 'Never',
            ]),
            'filters' => $filters,
            'roles' => $this->userAccounts->roleOptions(),
        ]);
    }

    public function create(): View
    {
        return view('suadmin.users.form', [
            'title' => 'Add user',
            'user' => new User(['is_active' => true]),
            'defaultSite' => null,
            'roles' => $this->userAccounts->roleOptions(),
            'sites' => $this->userAccounts->siteOptions(),
        ]);
    }

    public function store(StoreUserAccountRequest $request): RedirectResponse
    {
        $user = $this->userAccounts->create($this->accountData($request->validated()));

        return redirect()->route('suadmin.users.index')->with('success', "Account for {$user->name} created.");
    }

    public function edit(User $user): View
    {
        return view('suadmin.users.form', [
            'title' => "Edit {$user->name}",
            'user' => $user,
            'defaultSite' => $this->userAccounts->defaultSiteName($user),
            'roles' => $this->userAccounts->roleOptions(),
            'sites' => $this->userAccounts->siteOptions(),
        ]);
    }

    public function update(UpdateUserAccountRequest $request, User $user): RedirectResponse
    {
        try {
            $this->userAccounts->update($user, $this->accountData($request->validated()));
        } catch (BusinessRuleException $exception) {
            return redirect()->route('suadmin.users.edit', $user)->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('suadmin.users.index')->with('success', "Account for {$user->name} updated.");
    }

    public function deactivate(User $user): RedirectResponse
    {
        try {
            $this->userAccounts->deactivate($user);
        } catch (BusinessRuleException $exception) {
            return redirect()->route('suadmin.users.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('suadmin.users.index')->with('success', "{$user->name} deactivated and signed out.");
    }

    private function accountData(array $validated): array
    {
        return [
            ...$validated,
            'role' => filled($validated['role_id'] ?? null) ? Role::query()->active()->findOrFail($validated['role_id']) : null,
        ];
    }
}
