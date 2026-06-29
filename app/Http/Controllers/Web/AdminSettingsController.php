<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\UserSiteAccess;
use App\Services\SystemConfigurationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
            'settingGroups' => $this->systemConfiguration->settingGroups(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $action = $request->input('settings_action', 'save_settings');

        return match ($action) {
            'create_site' => $this->createSite($request),
            'create_document_series' => $this->createDocumentSeries($request),
            'create_user' => $this->createUser($request),
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
}
