<?php

namespace App\Providers;

use App\Models\Site;
use App\Models\User;
use App\Services\AlertService;
use App\Services\PackageService;
use App\Services\SiteAccessService;
use App\Services\SystemConfigurationService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // @feature('csv_exports') ... @endfeature: display only; routes and services enforce the same package rules.
        Blade::if('feature', fn (string $feature): bool => app(PackageService::class)->has($feature));

        View::composer(['layouts.auth', 'auth.*', 'layouts.suadmin'], function ($view): void {
            $view->with('appSystem', app(SystemConfigurationService::class)->headerContext());
        });

        View::composer('layouts.pos', function ($view): void {
            $view->with('appSystem', app(SystemConfigurationService::class)->headerContext(auth()->user()));
        });

        View::composer('layouts.app', function ($view): void {
            $systemConfiguration = app(SystemConfigurationService::class);
            $alertService = app(AlertService::class);
            $siteAccessService = app(SiteAccessService::class);
            $user = auth()->user();
            $currentSite = $this->currentSite($user);
            $appSystem = $systemConfiguration->headerContext($user);

            if ($currentSite) {
                $appSystem['site_name'] = $currentSite->name;
                $appSystem['kicker'] = trim($currentSite->name.' operations');
            }

            $view->with([
                'appSystem' => $appSystem,
                'notificationSummary' => $user
                    ? $alertService->summary($siteAccessService->scopeFilters(
                        $user,
                        $currentSite ? ['site_id' => $currentSite->id] : []
                    ))
                    : $alertService->summary(['site_ids' => []]),
                // Single-branch packages have nothing to switch between.
                'globalSiteOptions' => app(PackageService::class)->has('multi_branch') ? $this->siteOptions($user) : [],
                'globalCurrentSiteId' => $currentSite?->id,
                'globalCanChangeSiteDirectly' => $this->isAdmin($user),
            ]);
        });
    }

    private function currentSite(?User $user): ?Site
    {
        $sessionSiteId = request()->session()->get('pos_site_id');
        $allowedSiteIds = $user
            ? app(SiteAccessService::class)->allowedSiteIds($user, SiteAccessService::MAKE_SALES)
            : [];
        $site = $sessionSiteId
            ? Site::query()->active()->whereIn('id', $allowedSiteIds)->find($sessionSiteId)
            : null;

        if (! $site && $user) {
            $site = $user
                ->accessibleSites()
                ->wherePivot('is_active', true)
                ->wherePivot('can_make_sales', true)
                ->wherePivot('is_default', true)
                ->where('sites.is_active', true)
                ->orderBy('sites.name')
                ->first();
        }

        return $site ?? Site::query()->active()->whereIn('id', $allowedSiteIds)->orderBy('name')->first();
    }

    private function siteOptions(?User $user): array
    {
        $siteIds = $user
            ? app(SiteAccessService::class)->allowedSiteIds($user, SiteAccessService::MAKE_SALES)
            : [];

        return Site::query()
            ->active()
            ->whereIn('id', $siteIds)
            ->orderBy('name')
            ->get()
            ->map(fn (Site $site): array => [
                'id' => $site->id,
                'label' => trim("{$site->name} {$site->code}"),
            ])
            ->all();
    }

    private function isAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $user->loadMissing('role');
        $permissions = $user->role?->permissions ?? [];

        return in_array('*', $permissions, true)
            || str($user->role?->name ?? '')->lower()->contains('admin');
    }
}
