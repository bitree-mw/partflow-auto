<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\CarMake;
use App\Models\PaymentAccount;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\UserSiteAccess;
use App\Models\VehicleModel;
use App\Services\SystemConfigurationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminSettingsController extends Controller
{
    public function __construct(
        private readonly SystemConfigurationService $systemConfiguration
    ) {}

    public function index(Request $request): View
    {
        return view('settings.index', [
            'title' => 'Application Settings',
            'description' => 'Configure company identity, users, access, and stock operation defaults.',
            'settings' => $this->systemConfiguration->settings(),
            'sites' => $this->systemConfiguration->sites(),
            'countries' => config('countries'),
            'currencies' => ['MWK', 'USD', 'ZAR', 'EUR', 'GBP', 'JPY', 'CNY', 'AED'],
            'costingMethods' => ['Last purchase cost', 'Weighted average cost', 'Manual standard cost'],
            'roles' => $this->systemConfiguration->roles(),
            'users' => $this->systemConfiguration->users(),
            'carMakes' => $this->carMakes($request),
            'vehicleModels' => $this->vehicleModels($request),
            'carMakeOptions' => $this->carMakeOptions(),
            'paymentAccounts' => $this->paymentAccounts($request),
            'accountTypes' => $this->accountTypes(),
        ]);
    }

    public function showVehicleMake(Request $request, CarMake $carMake): View
    {
        $filters = $request->only('search', 'is_active');
        $models = $carMake->vehicleModels()
            ->search($filters['search'] ?? null)
            ->when(($filters['is_active'] ?? '') !== '', fn ($query) => $query->where('is_active', (bool) (int) $filters['is_active']))
            ->withCount('carModels')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (VehicleModel $model): array => $this->vehicleModelRow($model));

        return view('settings.vehicle-make', [
            'title' => $carMake->name,
            'description' => 'Manage model dropdowns for this make.',
            'carMake' => $carMake,
            'models' => $models,
            'filters' => $filters,
        ]);
    }

    public function storeVehicleModelForMake(Request $request, CarMake $carMake): RedirectResponse
    {
        $validated = $request->validate($this->vehicleModelRules($carMake->id, null, $request->integer('vehicle_year') ?: null), [
            'vehicle_model_code.regex' => 'The model code may only contain letters, numbers, and hyphens.',
        ]);

        VehicleModel::query()->create([
            'car_make_id' => $carMake->id,
            'name' => $validated['vehicle_model_name'],
            'code' => $this->uniqueVehicleModelCode($carMake->id, $validated['vehicle_model_code'] ?? null, $validated['vehicle_model_name']),
            'year' => $validated['vehicle_year'] ?? null,
            'body_style' => $validated['vehicle_body_style'] ?? null,
            'description' => $validated['vehicle_model_description'] ?? null,
            'is_active' => true,
        ]);

        return redirect()
            ->route('web.settings.vehicle-makes.show', $carMake)
            ->with('success', 'Vehicle model added successfully.');
    }

    public function updateVehicleModelForMake(Request $request, VehicleModel $vehicleModel): RedirectResponse
    {
        $validated = $request->validate($this->vehicleModelRules($vehicleModel->car_make_id, $vehicleModel, $request->integer('vehicle_year') ?: null), [
            'vehicle_model_code.regex' => 'The model code may only contain letters, numbers, and hyphens.',
        ]);

        $vehicleModel->update([
            'name' => $validated['vehicle_model_name'],
            'code' => $this->uniqueVehicleModelCodeForUpdate($vehicleModel, $vehicleModel->car_make_id, $validated['vehicle_model_code'] ?? null, $validated['vehicle_model_name']),
            'year' => $validated['vehicle_year'] ?? null,
            'body_style' => $validated['vehicle_body_style'] ?? null,
            'description' => $validated['vehicle_model_description'] ?? null,
        ]);

        return redirect()
            ->route('web.settings.vehicle-makes.show', $vehicleModel->car_make_id)
            ->with('success', 'Vehicle model updated successfully.');
    }

    public function deactivateVehicleModelForMake(VehicleModel $vehicleModel): RedirectResponse
    {
        if ($vehicleModel->carModels()->exists()) {
            return redirect()
                ->route('web.settings.vehicle-makes.show', $vehicleModel->car_make_id)
                ->with('error', 'This vehicle model is linked to fitments and cannot be made inactive.');
        }

        $vehicleModel->update(['is_active' => false]);

        return redirect()
            ->route('web.settings.vehicle-makes.show', $vehicleModel->car_make_id)
            ->with('success', 'Vehicle model marked inactive.');
    }

    public function update(Request $request): RedirectResponse
    {
        $action = $request->input('settings_action', 'save_settings');

        return match ($action) {
            'create_site' => $this->createSite($request),
            'create_user' => $this->createUser($request),
            'update_user' => $this->updateUser($request),
            'deactivate_user' => $this->deactivateUser($request),
            'create_car_make' => $this->createCarMake($request),
            'create_vehicle_model' => $this->createVehicleModel($request),
            'update_car_make' => $this->updateCarMake($request),
            'deactivate_car_make' => $this->deactivateCarMake($request),
            'update_vehicle_model' => $this->updateVehicleModel($request),
            'deactivate_vehicle_model' => $this->deactivateVehicleModel($request),
            default => $this->saveSettings($request),
        };
    }

    private function createSite(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:255', 'unique:sites,name'],
            'site_code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/', 'unique:sites,code'],
            'site_type' => ['required', 'string', 'in:shop,branch,warehouse'],
            'site_city' => ['nullable', 'string', 'max:255'],
            'site_country' => ['nullable', 'string', 'max:100'],
            'settings_panel' => ['nullable', 'string', 'max:80'],
        ], [
            'site_code.regex' => 'The site code may only contain letters, numbers, and hyphens.',
        ]);

        Site::query()->create([
            'name' => $validated['site_name'],
            'code' => filled($validated['site_code'] ?? null)
                ? strtoupper($validated['site_code'])
                : $this->uniqueSiteCode($validated['site_name']),
            'type' => $validated['site_type'],
            'location' => collect([$validated['site_city'] ?? null, $validated['site_country'] ?? null])
                ->filter()
                ->join(', '),
            'is_active' => true,
        ]);

        return $this->settingsRedirect($validated['settings_panel'] ?? 'company-sites', 'Site added successfully.');
    }

    private function saveSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'base_country' => ['nullable', 'string', 'max:100'],
            'base_currency' => ['required', 'string', 'max:10'],
            'default_branch' => ['nullable', 'string', 'max:255'],
            'stock_costing_method' => ['nullable', 'string', 'max:100'],
            'low_stock_policy' => ['nullable', 'string', 'max:255'],
            'settings_panel' => ['nullable', 'string', 'max:80'],
        ]);

        collect($validated)
            ->except('settings_panel')
            ->each(fn (mixed $value, string $key): BusinessSetting => $this->putSetting($key, $value));

        return $this->settingsRedirect($validated['settings_panel'] ?? 'company-profile', 'Settings saved successfully.');
    }

    private function createUser(Request $request): RedirectResponse
    {
        if ($request->input('user_site') === 'All sites') {
            $request->merge(['user_site' => null]);
        }

        $validated = $request->validate([
            'user_name' => ['required', 'string', 'max:255'],
            'user_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'user_role' => [
                'nullable',
                'string',
                'max:255',
                Rule::exists('roles', 'name')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')),
            ],
            'user_site' => [
                'nullable',
                'string',
                'max:255',
                Rule::exists('sites', 'name')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')),
            ],
            'user_password' => ['nullable', 'string', 'min:8', 'max:255'],
            'settings_panel' => ['nullable', 'string', 'max:80'],
        ]);

        $role = filled($validated['user_role'] ?? null)
            ? Role::query()->active()->where('name', $validated['user_role'])->firstOrFail()
            : null;

        DB::transaction(function () use ($validated, $role): void {
            $user = User::query()->create([
                'role_id' => $role?->id,
                'name' => $validated['user_name'],
                'username' => $this->generateUsername($validated['user_email']),
                'email' => strtolower($validated['user_email']),
                'password' => Hash::make($validated['user_password'] ?? Str::random(12)),
                'is_active' => true,
            ]);

            $this->syncUserSite($user, $validated['user_site'] ?? null);
        });

        return $this->settingsRedirect('user-management', 'User added successfully.');
    }

    private function updateUser(Request $request): RedirectResponse
    {
        if ($request->input('edit_user_site') === 'All sites') {
            $request->merge(['edit_user_site' => null]);
        }

        $user = User::query()->findOrFail($request->input('edit_user_id'));
        $validated = $request->validate([
            'edit_user_id' => ['required', 'integer', 'exists:users,id'],
            'edit_user_name' => ['required', 'string', 'max:255'],
            'edit_user_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'edit_user_role' => [
                'nullable',
                'string',
                'max:255',
                Rule::exists('roles', 'name')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')),
            ],
            'edit_user_site' => [
                'nullable',
                'string',
                'max:255',
                Rule::exists('sites', 'name')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')),
            ],
            'edit_user_password' => ['nullable', 'string', 'min:8', 'max:255'],
            'edit_user_is_active' => ['nullable', 'boolean'],
        ]);

        $role = filled($validated['edit_user_role'] ?? null)
            ? Role::query()->active()->where('name', $validated['edit_user_role'])->firstOrFail()
            : null;
        $isActive = $request->boolean('edit_user_is_active');

        if ((int) $user->id === (int) $request->user()->id && ! $isActive) {
            return $this->settingsRedirect('user-management', 'You cannot deactivate your own account.', 'error');
        }

        if ($this->wouldRemoveLastAdministrator($user, $role, $isActive)) {
            return $this->settingsRedirect('user-management', 'At least one active system administrator must remain.', 'error');
        }

        $passwordChanged = filled($validated['edit_user_password'] ?? null);

        DB::transaction(function () use ($user, $validated, $role, $isActive, $passwordChanged): void {
            $attributes = [
                'role_id' => $role?->id,
                'name' => $validated['edit_user_name'],
                'email' => strtolower($validated['edit_user_email']),
                'is_active' => $isActive,
            ];

            if ($passwordChanged) {
                $attributes['password'] = Hash::make($validated['edit_user_password']);
            }

            $user->update($attributes);
            $this->syncUserSite($user, $validated['edit_user_site'] ?? null);

            if (! $isActive || $passwordChanged) {
                $this->revokeUserAccess($user);
            }
        });

        return $this->settingsRedirect('user-management', 'User updated successfully.');
    }

    private function deactivateUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);
        $user = User::query()->with('role')->findOrFail($validated['user_id']);

        if ((int) $user->id === (int) $request->user()->id) {
            return $this->settingsRedirect('user-management', 'You cannot deactivate your own account.', 'error');
        }

        if ($this->wouldRemoveLastAdministrator($user, $user->role, false)) {
            return $this->settingsRedirect('user-management', 'At least one active system administrator must remain.', 'error');
        }

        DB::transaction(function () use ($user): void {
            $user->update(['is_active' => false]);
            $this->revokeUserAccess($user);
        });

        return $this->settingsRedirect('user-management', 'User deactivated successfully.');
    }

    private function syncUserSite(User $user, ?string $siteName): void
    {
        $user->siteAccesses()->update([
            'is_default' => false,
            'is_active' => false,
        ]);

        if (! filled($siteName) || $siteName === 'All sites') {
            return;
        }

        $site = Site::query()->active()->where('name', $siteName)->firstOrFail();
        $access = UserSiteAccess::withTrashed()->firstOrNew([
            'user_id' => $user->id,
            'site_id' => $site->id,
        ]);

        if ($access->trashed()) {
            $access->restore();
        }

        $access->fill([
            'access_level' => $access->access_level ?: 'manager',
            'can_view_stock' => $access->exists ? $access->can_view_stock : true,
            'can_make_sales' => $access->exists ? $access->can_make_sales : true,
            'can_receive_stock' => $access->exists ? $access->can_receive_stock : true,
            'can_transfer_stock' => $access->exists ? $access->can_transfer_stock : false,
            'can_adjust_stock' => $access->exists ? $access->can_adjust_stock : false,
            'is_default' => true,
            'is_active' => true,
        ])->save();
    }

    private function wouldRemoveLastAdministrator(User $user, ?Role $newRole, bool $willRemainActive): bool
    {
        $isAdministrator = in_array('*', $user->role?->permissions ?? [], true);
        $willBeAdministrator = $willRemainActive && in_array('*', $newRole?->permissions ?? [], true);

        if (! $isAdministrator || $willBeAdministrator) {
            return false;
        }

        return User::query()
            ->active()
            ->whereKeyNot($user->id)
            ->with('role')
            ->get()
            ->doesntContain(fn (User $candidate): bool => in_array('*', $candidate->role?->permissions ?? [], true));
    }

    private function revokeUserAccess(User $user): void
    {
        $user->tokens()->delete();

        if (config('session.driver') !== 'database') {
            return;
        }

        $sessionTable = config('session.table', 'sessions');

        if (Schema::hasTable($sessionTable)) {
            DB::table($sessionTable)->where('user_id', $user->id)->delete();
        }
    }

    private function generateUsername(string $email): string
    {
        $localPart = Str::before($email, '@');
        $baseUsername = Str::slug($localPart) ?: 'user';
        $username = $baseUsername;
        $suffix = 2;

        while (User::query()->where('username', $username)->exists()) {
            $username = "{$baseUsername}{$suffix}";
            $suffix++;
        }

        return $username;
    }

    private function createCarMake(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'make_name' => ['required', 'string', 'max:255', 'unique:car_makes,name'],
            'make_code' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9\-]+$/', 'unique:car_makes,code'],
            'make_description' => ['nullable', 'string'],
            'settings_panel' => ['nullable', 'string', 'max:80'],
        ], [
            'make_code.regex' => 'The make code may only contain letters, numbers, and hyphens.',
        ]);

        CarMake::query()->create([
            'name' => $validated['make_name'],
            'code' => $this->uniqueMakeCode($validated['make_code'] ?? null, $validated['make_name']),
            'description' => $validated['make_description'] ?? null,
            'is_active' => true,
        ]);

        return $this->settingsRedirect('vehicle-library', 'Car make added successfully.');
    }

    private function createVehicleModel(Request $request): RedirectResponse
    {
        $make = CarMake::query()
            ->where('name', $request->input('vehicle_make_name'))
            ->first();

        $validated = $request->validate([
            'vehicle_make_name' => ['required', 'string', 'max:255', 'exists:car_makes,name'],
            'vehicle_model_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('vehicle_models', 'name')->where('car_make_id', $make?->id),
            ],
            'vehicle_model_code' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9\-]+$/',
                Rule::unique('vehicle_models', 'code')->where('car_make_id', $make?->id),
            ],
            'vehicle_year' => ['nullable', 'integer', 'min:1900', 'max:'.((int) date('Y') + 1)],
            'vehicle_body_style' => ['nullable', 'string', 'max:100'],
            'vehicle_model_description' => ['nullable', 'string'],
            'settings_panel' => ['nullable', 'string', 'max:80'],
        ], [
            'vehicle_model_code.regex' => 'The model code may only contain letters, numbers, and hyphens.',
        ]);

        VehicleModel::query()->create([
            'car_make_id' => $make->id,
            'name' => $validated['vehicle_model_name'],
            'code' => $this->uniqueVehicleModelCode(
                $make->id,
                $validated['vehicle_model_code'] ?? null,
                $validated['vehicle_model_name']
            ),
            'year' => $validated['vehicle_year'] ?? null,
            'body_style' => $validated['vehicle_body_style'] ?? null,
            'description' => $validated['vehicle_model_description'] ?? null,
            'is_active' => true,
        ]);

        return $this->settingsRedirect('vehicle-library', 'Vehicle model added successfully.');
    }

    private function updateCarMake(Request $request): RedirectResponse
    {
        $make = CarMake::query()->findOrFail($request->input('edit_make_id'));
        $validated = $request->validate([
            'edit_make_id' => ['required', 'integer', 'exists:car_makes,id'],
            'edit_make_name' => ['required', 'string', 'max:255', Rule::unique('car_makes', 'name')->ignore($make->id)],
            'edit_make_code' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('car_makes', 'code')->ignore($make->id)],
            'edit_make_description' => ['nullable', 'string'],
        ], [
            'edit_make_code.regex' => 'The make code may only contain letters, numbers, and hyphens.',
        ]);

        $make->update([
            'name' => $validated['edit_make_name'],
            'code' => $this->uniqueMakeCodeForUpdate($make, $validated['edit_make_code'] ?? null, $validated['edit_make_name']),
            'description' => $validated['edit_make_description'] ?? null,
        ]);

        return $this->settingsRedirect('vehicle-library', 'Car make updated successfully.');
    }

    private function deactivateCarMake(Request $request): RedirectResponse
    {
        $make = CarMake::query()->findOrFail($request->query('car_make_id'));

        if ($make->vehicleModels()->where('is_active', true)->exists() || $make->carModels()->exists()) {
            return $this->settingsRedirect('vehicle-library', 'This make is linked to models or fitments and cannot be made inactive.', 'error');
        }

        $make->update(['is_active' => false]);

        return $this->settingsRedirect('vehicle-library', 'Car make marked inactive.');
    }

    private function updateVehicleModel(Request $request): RedirectResponse
    {
        $model = VehicleModel::query()->findOrFail($request->input('edit_vehicle_model_id'));
        $make = CarMake::query()
            ->where('name', $request->input('edit_vehicle_make_name'))
            ->first();

        $validated = $request->validate([
            'edit_vehicle_model_id' => ['required', 'integer', 'exists:vehicle_models,id'],
            'edit_vehicle_make_name' => ['required', 'string', 'max:255', 'exists:car_makes,name'],
            'edit_vehicle_model_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('vehicle_models', 'name')->where('car_make_id', $make?->id)->ignore($model->id),
            ],
            'edit_vehicle_model_code' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9\-]+$/',
                Rule::unique('vehicle_models', 'code')->where('car_make_id', $make?->id)->ignore($model->id),
            ],
            'edit_vehicle_year' => ['nullable', 'integer', 'min:1900', 'max:'.((int) date('Y') + 1)],
            'edit_vehicle_body_style' => ['nullable', 'string', 'max:100'],
            'edit_vehicle_model_description' => ['nullable', 'string'],
        ], [
            'edit_vehicle_model_code.regex' => 'The model code may only contain letters, numbers, and hyphens.',
        ]);

        $model->update([
            'car_make_id' => $make->id,
            'name' => $validated['edit_vehicle_model_name'],
            'code' => $this->uniqueVehicleModelCodeForUpdate(
                $model,
                $make->id,
                $validated['edit_vehicle_model_code'] ?? null,
                $validated['edit_vehicle_model_name']
            ),
            'year' => $validated['edit_vehicle_year'] ?? null,
            'body_style' => $validated['edit_vehicle_body_style'] ?? null,
            'description' => $validated['edit_vehicle_model_description'] ?? null,
        ]);

        return $this->settingsRedirect('vehicle-library', 'Vehicle model updated successfully.');
    }

    private function deactivateVehicleModel(Request $request): RedirectResponse
    {
        $model = VehicleModel::query()->findOrFail($request->query('vehicle_model_id'));

        if ($model->carModels()->exists()) {
            return $this->settingsRedirect('vehicle-library', 'This vehicle model is linked to fitments and cannot be made inactive.', 'error');
        }

        $model->update(['is_active' => false]);

        return $this->settingsRedirect('vehicle-library', 'Vehicle model marked inactive.');
    }

    private function putSetting(string $key, mixed $value): BusinessSetting
    {
        return BusinessSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    private function settingsRedirect(string $panel, string $message, string $flashKey = 'success'): RedirectResponse
    {
        return redirect(route('web.settings.index').'#'.$panel)
            ->with($flashKey, $message)
            ->with('settings_panel', $panel);
    }

    private function carMakes(Request $request)
    {
        $search = $request->query('vehicle_search');

        return CarMake::query()
            ->withCount('vehicleModels')
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(8, ['*'], 'makes_page')
            ->withQueryString()
            ->fragment('vehicle-library')
            ->through(fn (CarMake $make): array => [
                'id' => $make->id,
                'name' => $make->name,
                'code' => $make->code,
                'description' => $make->description ?: '',
                'models' => $make->vehicle_models_count,
                'is_active' => (bool) $make->is_active,
                'status' => $make->is_active ? 'Active' : 'Inactive',
            ]);
    }

    private function vehicleModels(Request $request)
    {
        return VehicleModel::query()
            ->with('carMake')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(8, ['*'], 'models_page')
            ->withQueryString()
            ->fragment('vehicle-library')
            ->through(fn (VehicleModel $model): array => [
                'id' => $model->id,
                'make_id' => $model->car_make_id,
                'make' => $model->carMake?->name ?? 'Unknown make',
                'name' => $model->name,
                'code' => $model->code,
                'year' => $model->year ?: 'Not set',
                'body_style' => $model->body_style ?: 'Not set',
                'description' => $model->description ?: '',
                'is_active' => (bool) $model->is_active,
                'status' => $model->is_active ? 'Active' : 'Inactive',
            ]);
    }

    private function carMakeOptions(): array
    {
        return CarMake::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (CarMake $make): array => [
                'id' => $make->id,
                'name' => $make->name,
            ])
            ->all();
    }

    private function paymentAccounts(Request $request)
    {
        return PaymentAccount::query()
            ->when($request->query('payment_search'), fn ($query, $search) => $query
                ->where('account_name', 'like', "%{$search}%")
                ->orWhere('account_holder_name', 'like', "%{$search}%"))
            ->orderByDesc('is_active')
            ->orderBy('account_name')
            ->paginate(8, ['*'], 'payment_page')
            ->withQueryString()
            ->fragment('payment-accounts');
    }

    private function accountTypes(): array
    {
        return [
            'cash' => 'Cash',
            'bank' => 'Bank',
            'mobile_money' => 'Mobile Money',
            'card' => 'Card',
        ];
    }

    private function vehicleModelRules(int $makeId, ?VehicleModel $model = null, ?int $year = null): array
    {
        return [
            'vehicle_model_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('vehicle_models', 'name')
                    ->where('car_make_id', $makeId)
                    ->where('year', $year)
                    ->ignore($model?->id),
            ],
            'vehicle_model_code' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9\-]+$/',
                Rule::unique('vehicle_models', 'code')->where('car_make_id', $makeId)->ignore($model?->id),
            ],
            'vehicle_year' => ['nullable', 'integer', 'min:1900', 'max:'.((int) date('Y') + 1)],
            'vehicle_body_style' => ['nullable', 'string', 'max:100'],
            'vehicle_model_description' => ['nullable', 'string'],
        ];
    }

    private function vehicleModelRow(VehicleModel $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model->name,
            'code' => $model->code,
            'year' => $model->year ?: 'Not set',
            'body_style' => $model->body_style ?: 'Not set',
            'description' => $model->description ?: '',
            'linked_fitments' => $model->car_models_count ?? 0,
            'is_active' => (bool) $model->is_active,
            'status' => $model->is_active ? 'Active' : 'Inactive',
        ];
    }

    private function makeCode(?string $code, string $name, int $length): string
    {
        return Str::of($code ?: $name)
            ->upper()
            ->replaceMatches('/[^A-Z0-9\-]+/', '')
            ->substr(0, $length)
            ->toString();
    }

    private function uniqueMakeCode(?string $code, string $name): string
    {
        $base = $this->makeCode($code, $name, 10) ?: 'MAKE';
        $candidate = $base;
        $suffix = 1;

        while (CarMake::query()->where('code', $candidate)->exists()) {
            $candidate = substr($base, 0, 8).str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);
            $suffix++;
        }

        return $candidate;
    }

    private function uniqueMakeCodeForUpdate(CarMake $make, ?string $code, string $name): string
    {
        $base = $this->makeCode($code, $name, 10) ?: 'MAKE';
        $candidate = $base;
        $suffix = 1;

        while (CarMake::query()->whereKeyNot($make->id)->where('code', $candidate)->exists()) {
            $candidate = substr($base, 0, 8).str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);
            $suffix++;
        }

        return $candidate;
    }

    private function uniqueVehicleModelCode(int $makeId, ?string $code, string $name): string
    {
        $base = $this->makeCode($code, $name, 20) ?: 'MODEL';
        $candidate = $base;
        $suffix = 1;

        while (VehicleModel::query()->where('car_make_id', $makeId)->where('code', $candidate)->exists()) {
            $candidate = substr($base, 0, 17).str_pad((string) $suffix, 3, '0', STR_PAD_LEFT);
            $suffix++;
        }

        return $candidate;
    }

    private function uniqueVehicleModelCodeForUpdate(VehicleModel $model, int $makeId, ?string $code, string $name): string
    {
        $base = $this->makeCode($code, $name, 20) ?: 'MODEL';
        $candidate = $base;
        $suffix = 1;

        while (VehicleModel::query()
            ->whereKeyNot($model->id)
            ->where('car_make_id', $makeId)
            ->where('code', $candidate)
            ->exists()) {
            $candidate = substr($base, 0, 17).str_pad((string) $suffix, 3, '0', STR_PAD_LEFT);
            $suffix++;
        }

        return $candidate;
    }

    private function uniqueSiteCode(string $name): string
    {
        $base = Str::of($name)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '')
            ->substr(0, 6)
            ->toString() ?: 'SITE';
        $candidate = $base;
        $suffix = 1;

        while (Site::query()->where('code', $candidate)->exists()) {
            $candidate = substr($base, 0, 6).str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);
            $suffix++;
        }

        return $candidate;
    }
}
