<?php

namespace App\Providers;

use App\Services\AlertService;
use App\Services\SystemConfigurationService;
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

            $view->with([
                'appSystem' => $systemConfiguration->headerContext(auth()->user()),
                'notificationSummary' => $alertService->summary(),
            ]);
        });
    }
}
