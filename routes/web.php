<?php

use App\Http\Controllers\Web\AdminSettingsController;
use App\Http\Controllers\Web\AlertsController;
use App\Http\Controllers\Web\CatalogController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\PaymentAccountController;
use App\Http\Controllers\Web\PosController;
use App\Http\Controllers\Web\ReportsController;
use App\Http\Controllers\Web\SalesController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('web.dashboard');
});

Route::get('/pos', [PosController::class, 'index'])->name('web.pos');

// Web back-office routes are kept separate from API routes and can receive auth/role middleware as the session UI grows.
Route::prefix('back-office')
    ->name('web.')
    ->group(function () {
        Route::get('/', fn () => redirect()->route('web.dashboard'));
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('sales', [SalesController::class, 'index'])->name('sales.index');
        Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');
        Route::get('alerts', [AlertsController::class, 'index'])->name('alerts.index');

        Route::prefix('catalog')->name('catalog.')->controller(CatalogController::class)->group(function () {
            Route::get('car-models', 'carModels')->name('car-models.index');
            Route::get('car-models/create', 'createCarModel')->name('car-models.create');
            Route::post('car-models', 'storeCarModel')->name('car-models.store');

            Route::get('part-types', 'partTypes')->name('part-types.index');
            Route::get('part-types/create', 'createPartType')->name('part-types.create');
            Route::post('part-types', 'storePartType')->name('part-types.store');

            Route::get('products', 'products')->name('products.index');
            Route::get('products/create', 'createProduct')->name('products.create');
            Route::post('products', 'storeProduct')->name('products.store');
        });

        Route::get('settings', [AdminSettingsController::class, 'index'])->name('settings.index');
        Route::post('settings', [AdminSettingsController::class, 'update'])->name('settings.update');

        Route::resource('payment-accounts', PaymentAccountController::class)
            ->parameters(['payment-accounts' => 'payment_account']);
    });
