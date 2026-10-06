<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\PaymentAccount;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\UserSiteAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SystemConfigurationService
{
    public const ALL_BRANCHES = 'All sites';

    public const COSTING_METHODS = ['Last purchase cost', 'Weighted average cost', 'Manual standard cost'];

    // PartFlow Auto currently serves Malawi only, so country and currency are fixed rather than configurable.
    public const BASE_COUNTRY = 'Malawi';

    public const BASE_CURRENCY = 'MWK';

    public function __construct(private readonly PackageService $packages) {}

    /**
     * @return list<string>
     */
    public function defaultBranchOptions(): array
    {
        return [self::ALL_BRANCHES, ...Site::query()->active()->orderBy('name')->pluck('name')->all()];
    }

    public function activePaymentAccountOptions(): Collection
    {
        return PaymentAccount::query()->active()->orderBy('account_name')->get(['id', 'account_name', 'account_type']);
    }

    /**
     * The payment account pre-selected at the POS, if it is still active.
     */
    public function defaultPosPaymentAccountId(): ?int
    {
        $id = (int) $this->setting('default_pos_payment_account_id');

        return $id > 0 && PaymentAccount::query()->active()->whereKey($id)->exists() ? $id : null;
    }

    public function settings(): array
    {
        $defaultSite = $this->defaultSite();

        return [
            'business_name' => $this->businessName(),
            'company_logo_url' => $this->companyLogoUrl(),
            'primary_color' => $this->themeColor('primary_color', '#0a1630'),
            'secondary_color' => $this->themeColor('secondary_color', '#f47a2a'),
            'tertiary_color' => $this->themeColor('tertiary_color', '#f5f6f8'),
            'legal_name' => $this->setting('legal_name', $this->businessName().' Limited'),
            'registration_number' => $this->setting('registration_number', config('services.partflow.registration_number', 'MW-BR-1042')),
            'base_country' => self::BASE_COUNTRY,
            'base_currency' => $this->currency(),
            'low_stock_notification_email' => $this->storedLowStockNotificationEmail(),
            'default_branch' => $this->setting('default_branch', $defaultSite?->name ?? self::ALL_BRANCHES),
            'default_pos_payment_account_id' => $this->defaultPosPaymentAccountId(),
            'stock_costing_method' => $this->setting('stock_costing_method', config('services.partflow.stock_costing_method', 'Last purchase cost')),
            'low_stock_policy' => $this->setting('low_stock_policy', 'Use product default unless branch override exists'),
            'maximum_discount_percentage' => (float) $this->setting(
                'maximum_discount_percentage',
                DiscountPolicyService::DEFAULT_MAXIMUM_DISCOUNT_PERCENTAGE
            ),
        ];
    }

    public function headerContext(?User $user = null): array
    {
        $defaultSite = $this->defaultSite($user);
        // Packages without custom branding render the default PartFlow logo and colours.
        $branded = $this->packages->has('custom_branding');
        $primaryColor = $branded ? $this->themeColor('primary_color', '#0a1630') : '#0a1630';
        $secondaryColor = $branded ? $this->themeColor('secondary_color', '#f47a2a') : '#f47a2a';

        return [
            'business_name' => $this->businessName(),
            'business_initials' => $this->initials($this->businessName()),
            'company_logo_url' => $branded ? $this->companyLogoUrl() : null,
            'primary_color' => $primaryColor,
            'secondary_color' => $secondaryColor,
            'tertiary_color' => $branded ? $this->themeColor('tertiary_color', '#f5f6f8') : '#f5f6f8',
            'on_primary_color' => $this->contrastingTextColor($primaryColor),
            'on_secondary_color' => $this->contrastingTextColor($secondaryColor),
            'tagline' => config('services.partflow.tagline', 'Auto parts operations'),
            'currency' => $this->currency(),
            'site_name' => $defaultSite?->name ?? 'All sites',
            'kicker' => trim(($defaultSite?->name ?? 'All sites').' operations'),
            'user_role' => $user?->role?->name ?? 'User',
        ];
    }

    public function sites(): Collection
    {
        $country = $this->settings()['base_country'];

        return Site::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Site $site) => [
                'name' => $site->name,
                'type' => str($site->type)->headline()->toString(),
                'city' => $site->location ?? 'Not set',
                'country' => $country,
                'status' => $site->is_active ? 'Active' : 'Inactive',
            ]);
    }

    public function roles(): Collection
    {
        return Role::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name');
    }

    public function users(): Collection
    {
        return User::query()
            ->with(['role', 'siteAccesses.site'])
            ->orderBy('name')
            ->get()
            ->map(function (User $user): array {
                $activeSiteAccesses = $user->siteAccesses->where('is_active', true);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role?->name ?? '',
                    'site' => $activeSiteAccesses->firstWhere('is_default', true)?->site?->name
                        ?? $activeSiteAccesses->first()?->site?->name
                        ?? 'All sites',
                    'is_active' => (bool) $user->is_active,
                    'status' => $user->is_active ? 'Active' : 'Inactive',
                ];
            });
    }

    public function settingGroups(): Collection
    {
        return collect([
            [
                'name' => 'Branches',
                'items' => Site::query()->orderBy('name')->pluck('name')->all(),
            ],
            [
                'name' => 'Catalogue setup',
                'items' => ['Car models', 'Fuel types', 'Product types', 'Products'],
            ],
            [
                'name' => 'Operations',
                'items' => ['POS sales', 'Purchases', 'Stock receiving', 'Transfers'],
            ],
            [
                'name' => 'Permissions',
                'items' => $this->roles()->all(),
            ],
        ]);
    }

    /**
     * Recipient for low-stock alerts and the weekly digest; null disables both, including on packages without them.
     */
    public function lowStockNotificationEmail(): ?string
    {
        return $this->packages->has('low_stock_emails') ? $this->storedLowStockNotificationEmail() : null;
    }

    private function storedLowStockNotificationEmail(): ?string
    {
        $email = $this->setting('low_stock_notification_email');

        return is_string($email) && filled($email) ? $email : null;
    }

    private function businessName(): string
    {
        $configuredName = config('app.name', 'PartFlow Auto');
        $savedName = $this->setting('business_name');

        if (is_string($savedName) && filled($savedName)) {
            return $savedName;
        }

        return $configuredName === 'Laravel' ? 'PartFlow Auto' : $configuredName;
    }

    private function currency(): string
    {
        return self::BASE_CURRENCY;
    }

    private function companyLogoUrl(): ?string
    {
        $path = $this->setting('company_logo_path');

        if (! is_string($path) || ! filled($path) || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    private function themeColor(string $key, string $default): string
    {
        $color = $this->setting($key, $default);

        return is_string($color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $color)
            ? strtolower($color)
            : $default;
    }

    private function contrastingTextColor(string $hexColor): string
    {
        $red = hexdec(substr($hexColor, 1, 2));
        $green = hexdec(substr($hexColor, 3, 2));
        $blue = hexdec(substr($hexColor, 5, 2));
        $luminance = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

        return $luminance > 150 ? '#17223a' : '#ffffff';
    }

    private function defaultSite(?User $user = null): ?Site
    {
        $access = UserSiteAccess::query()
            ->with('site')
            ->when($user, fn ($query) => $query->where('user_id', $user->id))
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        return $access?->site ?? Site::query()->active()->orderBy('name')->first();
    }

    private function initials(string $value): string
    {
        return collect(explode(' ', $value))
            ->filter()
            ->map(fn (string $part) => strtoupper(substr($part, 0, 1)))
            ->take(2)
            ->implode('');
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        if (! Schema::hasTable('business_settings')) {
            return $default;
        }

        $value = BusinessSetting::query()->where('key', $key)->first()?->value;

        return $value ?? $default;
    }
}
