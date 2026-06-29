<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\CarMake;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\UserSiteAccess;
use App\Models\VehicleModel;
use App\Services\SystemConfigurationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminSettingsController extends Controller
{
    public function __construct(
        private readonly SystemConfigurationService $systemConfiguration
    ) {}

    public function index(): View
    {
        return view('settings.index', [
            'title' => 'Application Settings',
            'description' => 'Configure company identity, sites, users, document numbering, and stock operation defaults.',
            'settings' => $this->systemConfiguration->settings(),
            'sites' => $this->systemConfiguration->sites(),
            'countries' => config('countries'),
            'currencies' => ['MWK', 'USD', 'ZAR', 'EUR', 'GBP', 'JPY', 'CNY', 'AED'],
            'siteTypes' => ['shop' => 'Shop', 'branch' => 'Branch', 'warehouse' => 'Warehouse'],
            'costingMethods' => ['Last purchase cost', 'Weighted average cost', 'Manual standard cost'],
            'documentSeries' => $this->systemConfiguration->documentSeries(),
            'roles' => $this->systemConfiguration->roles(),
            'users' => $this->systemConfiguration->users(),
            'carMakes' => $this->carMakes(),
            'vehicleModels' => $this->vehicleModels(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $action = $request->input('settings_action', 'save_settings');

        return match ($action) {
            'create_site' => $this->createSite($request),
            'create_document_series' => $this->createDocumentSeries($request),
            'create_user' => $this->createUser($request),
            'create_car_make' => $this->createCarMake($request),
            'create_vehicle_model' => $this->createVehicleModel($request),
            default => $this->saveSettings($request),
        };
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

    private function createSite(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:255', 'unique:sites,name'],
            'site_type' => ['required', 'string', 'in:shop,branch,warehouse'],
            'site_city' => ['nullable', 'string', 'max:255'],
            'site_country' => ['nullable', 'string', 'max:255'],
            'settings_panel' => ['nullable', 'string', 'max:80'],
        ]);

        Site::query()->create([
            'name' => $validated['site_name'],
            'code' => $this->uniqueSiteCode($validated['site_name']),
            'type' => $validated['site_type'],
            'location' => $validated['site_city'] ?? null,
            'address' => $validated['site_country'] ?? null,
            'is_active' => true,
        ]);

        return $this->settingsRedirect('company-sites', 'Site added successfully.');
    }

    private function createDocumentSeries(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'series_name' => ['required', 'string', 'max:255'],
            'series_prefix' => ['required', 'string', 'max:20'],
            'series_next_number' => ['required', 'integer', 'min:1'],
            'settings_panel' => ['nullable', 'string', 'max:80'],
        ]);

        $series = collect($this->getSetting('document_series', []))
            ->reject(fn (array $item): bool => strcasecmp($item['document'] ?? '', $validated['series_name']) === 0)
            ->push([
                'document' => $validated['series_name'],
                'type' => Str::of($validated['series_name'])->slug('_')->toString(),
                'prefix' => strtoupper($validated['series_prefix']),
                'next_number' => (int) $validated['series_next_number'],
            ])
            ->values()
            ->all();

        $this->putSetting('document_series', $series);

        return $this->settingsRedirect('document-numbering', 'Document series added successfully.');
    }

    private function createUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_name' => ['required', 'string', 'max:255'],
            'user_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'user_role' => ['nullable', 'string', 'max:255'],
            'user_site' => ['nullable', 'string', 'max:255'],
            'user_password' => ['nullable', 'string', 'min:8', 'max:255'],
            'settings_panel' => ['nullable', 'string', 'max:80'],
        ]);

        $role = filled($validated['user_role'] ?? null)
            ? Role::query()->firstOrCreate(
                ['name' => $validated['user_role']],
                ['permissions' => [], 'is_active' => true]
            )
            : null;

        $user = User::query()->create([
            'role_id' => $role?->id,
            'name' => $validated['user_name'],
            'email' => strtolower($validated['user_email']),
            'password' => Hash::make($validated['user_password'] ?? Str::random(12)),
            'is_active' => true,
        ]);

        if (filled($validated['user_site'] ?? null) && $site = Site::query()->where('name', $validated['user_site'])->first()) {
            UserSiteAccess::query()->create([
                'user_id' => $user->id,
                'site_id' => $site->id,
                'access_level' => 'manager',
                'can_view_stock' => true,
                'can_make_sales' => true,
                'can_receive_stock' => true,
                'can_transfer_stock' => false,
                'can_adjust_stock' => false,
                'is_default' => true,
                'is_active' => true,
            ]);
        }

        return $this->settingsRedirect('user-management', 'User added successfully.');
    }

    private function createCarMake(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'make_name' => ['required', 'string', 'max:255', 'unique:car_makes,name'],
            'make_code' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9\-]+$/', 'unique:car_makes,code'],
            'make_country' => ['nullable', 'string', 'max:100'],
            'make_description' => ['nullable', 'string'],
            'settings_panel' => ['nullable', 'string', 'max:80'],
        ], [
            'make_code.regex' => 'The make code may only contain letters, numbers, and hyphens.',
        ]);

        CarMake::query()->create([
            'name' => $validated['make_name'],
            'code' => $this->uniqueMakeCode($validated['make_code'] ?? null, $validated['make_name']),
            'country' => $validated['make_country'] ?? null,
            'description' => $validated['make_description'] ?? null,
            'is_active' => true,
        ]);

        return $this->settingsRedirect('vehicle-library', 'Car make added successfully.');
    }

    private function createVehicleModel(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'vehicle_make_id' => ['required', 'integer', 'exists:car_makes,id'],
            'vehicle_model_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('vehicle_models', 'name')->where('car_make_id', $request->input('vehicle_make_id')),
            ],
            'vehicle_model_code' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9\-]+$/',
                Rule::unique('vehicle_models', 'code')->where('car_make_id', $request->input('vehicle_make_id')),
            ],
            'vehicle_start_year' => ['nullable', 'integer', 'min:1900', 'max:'.((int) date('Y') + 1)],
            'vehicle_end_year' => ['nullable', 'integer', 'min:1900', 'max:'.((int) date('Y') + 1)],
            'vehicle_body_style' => ['nullable', 'string', 'max:100'],
            'vehicle_model_description' => ['nullable', 'string'],
            'settings_panel' => ['nullable', 'string', 'max:80'],
        ], [
            'vehicle_model_code.regex' => 'The model code may only contain letters, numbers, and hyphens.',
        ]);

        VehicleModel::query()->create([
            'car_make_id' => $validated['vehicle_make_id'],
            'name' => $validated['vehicle_model_name'],
            'code' => $this->uniqueVehicleModelCode(
                (int) $validated['vehicle_make_id'],
                $validated['vehicle_model_code'] ?? null,
                $validated['vehicle_model_name']
            ),
            'start_year' => $validated['vehicle_start_year'] ?? null,
            'end_year' => $validated['vehicle_end_year'] ?? null,
            'body_style' => $validated['vehicle_body_style'] ?? null,
            'description' => $validated['vehicle_model_description'] ?? null,
            'is_active' => true,
        ]);

        return $this->settingsRedirect('vehicle-library', 'Vehicle model added successfully.');
    }

    private function putSetting(string $key, mixed $value): BusinessSetting
    {
        return BusinessSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    private function getSetting(string $key, mixed $default = null): mixed
    {
        return BusinessSetting::query()->where('key', $key)->first()?->value ?? $default;
    }

    private function settingsRedirect(string $panel, string $message): RedirectResponse
    {
        return redirect(route('web.settings.index').'#'.$panel)
            ->with('success', $message)
            ->with('settings_panel', $panel);
    }

    private function uniqueSiteCode(string $name): string
    {
        $base = Str::of($name)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '')
            ->substr(0, 6)
            ->toString() ?: 'SITE';

        $code = $base;
        $suffix = 1;

        while (Site::query()->where('code', $code)->exists()) {
            $code = $base.str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);
            $suffix++;
        }

        return $code;
    }

    private function carMakes(): array
    {
        return CarMake::query()
            ->withCount('vehicleModels')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (CarMake $make): array => [
                'id' => $make->id,
                'name' => $make->name,
                'code' => $make->code,
                'country' => $make->country ?: 'Not set',
                'models' => $make->vehicle_models_count,
                'status' => $make->is_active ? 'Active' : 'Inactive',
            ])
            ->all();
    }

    private function vehicleModels(): array
    {
        return VehicleModel::query()
            ->with('carMake')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (VehicleModel $model): array => [
                'make_id' => $model->car_make_id,
                'make' => $model->carMake?->name ?? 'Unknown make',
                'name' => $model->name,
                'code' => $model->code,
                'years' => collect([$model->start_year, $model->end_year])->filter()->join(' - ') ?: 'Any years',
                'body_style' => $model->body_style ?: 'Not set',
                'status' => $model->is_active ? 'Active' : 'Inactive',
            ])
            ->all();
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
}
