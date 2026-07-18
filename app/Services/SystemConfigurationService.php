<?php

namespace App\Services;

use App\Models\InventoryDocument;
use App\Models\BusinessSetting;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\UserSiteAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SystemConfigurationService
{
    public function settings(): array
    {
        $defaultSite = $this->defaultSite();

        return [
            'business_name' => $this->businessName(),
            'legal_name' => $this->setting('legal_name', $this->businessName().' Limited'),
            'registration_number' => $this->setting('registration_number', config('services.partflow.registration_number', 'MW-BR-1042')),
            'base_country' => $this->setting('base_country', config('services.partflow.base_country', 'Malawi')),
            'base_currency' => $this->currency(),
            'default_branch' => $this->setting('default_branch', $defaultSite?->name ?? 'All sites'),
            'stock_costing_method' => $this->setting('stock_costing_method', config('services.partflow.stock_costing_method', 'Last purchase cost')),
            'low_stock_policy' => $this->setting('low_stock_policy', 'Use product default unless branch override exists'),
        ];
    }

    public function headerContext(?User $user = null): array
    {
        $defaultSite = $this->defaultSite($user);

        return [
            'business_name' => $this->businessName(),
            'business_initials' => $this->initials($this->businessName()),
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
            ->map(fn (User $user) => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->name ?? 'User',
                'site' => $user->siteAccesses->firstWhere('is_default', true)?->site?->name
                    ?? $user->siteAccesses->first()?->site?->name
                    ?? 'All sites',
                'status' => $user->is_active ? 'Active' : 'Inactive',
            ]);
    }

    public function documentSeries(): Collection
    {
        $defaultSeries = collect([
            ['document' => 'POS Sales', 'type' => 'sale', 'prefix' => 'POS'],
            ['document' => 'Purchases', 'type' => 'purchase', 'prefix' => 'PUR'],
            ['document' => 'Transfers', 'type' => 'transfer', 'prefix' => 'TRF'],
            ['document' => 'Stock Takes', 'type' => 'stock_take', 'prefix' => 'STK'],
        ]);

        return $defaultSeries
            ->merge(collect($this->setting('document_series', [])))
            ->map(function (array $series) {
            $count = InventoryDocument::query()
                ->where('document_type', $series['type'] ?? str($series['document'])->slug('_')->toString())
                ->count();

            return [
                'document' => $series['document'],
                'prefix' => $series['prefix'],
                'next_number' => (string) ($series['next_number'] ?? ($count + 1)),
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
        return $this->setting('base_currency', config('services.partflow.base_currency', 'MWK'));
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
