<?php

namespace App\Providers;

use App\Services\AlertService;
use App\Services\SystemConfigurationService;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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
        View::composer('layouts.app', function ($view): void {
            $systemConfiguration = app(SystemConfigurationService::class);
            $alertService = app(AlertService::class);
            $user = auth()->user();
            $currentSite = $this->currentSite($user);
            $appSystem = $systemConfiguration->headerContext($user);

            if ($currentSite) {
                $appSystem['site_name'] = $currentSite->name;
                $appSystem['kicker'] = trim($currentSite->name.' operations');
            }

            $view->with([
                'appSystem' => $appSystem,
                'notificationSummary' => $alertService->summary(),
                'globalSiteOptions' => $this->siteOptions(),
                'globalCurrentSiteId' => $currentSite?->id,
                'globalCanChangeSiteDirectly' => $this->isAdmin($user),
            ]);
        });
    }

    private function currentSite(?User $user): ?Site
    {
        $sessionSiteId = request()->session()->get('pos_site_id');
        $site = $sessionSiteId ? Site::query()->active()->find($sessionSiteId) : null;

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

        return $site ?? Site::query()->active()->orderBy('name')->first();
    }

    private function siteOptions(): array
    {
        return Site::query()
            ->active()
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
