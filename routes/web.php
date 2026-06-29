<?php

use App\Http\Controllers\Web\AdminSettingsController;
use App\Http\Controllers\Web\AlertsController;
use App\Http\Controllers\Web\AuthSessionController;
use App\Http\Controllers\Web\CatalogController;
use App\Http\Controllers\Web\ContactDirectoryController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\PaymentAccountController;
use App\Http\Controllers\Web\PosController;
use App\Http\Controllers\Web\PurchasesController;
use App\Http\Controllers\Web\ReportsController;
use App\Http\Controllers\Web\SalesController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('web.dashboard');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthSessionController::class, 'destroy'])->name('logout');
    Route::get('/pos', [PosController::class, 'index'])->name('web.pos');
    Route::post('/pos/sales', [PosController::class, 'store'])->name('web.pos.sales');
});

// Web back-office routes are kept separate from API routes and receive auth/role middleware as the session UI grows.
Route::prefix('back-office')
    ->name('web.')
    ->middleware('auth')
    ->group(function () {
        Route::get('/', fn () => redirect()->route('web.dashboard'));
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('sales', [SalesController::class, 'index'])->name('sales.index');
        Route::get('purchases', [PurchasesController::class, 'index'])->name('purchases.index');
        Route::get('purchases/create', [PurchasesController::class, 'create'])->name('purchases.create');
        Route::post('purchases', [PurchasesController::class, 'store'])->name('purchases.store');
        Route::get('customers', [ContactDirectoryController::class, 'customers'])->name('customers.index');
        Route::get('customers/create', [ContactDirectoryController::class, 'createCustomer'])->name('customers.create');
        Route::post('customers', [ContactDirectoryController::class, 'storeCustomer'])->name('customers.store');
        Route::get('suppliers', [ContactDirectoryController::class, 'suppliers'])->name('suppliers.index');
        Route::get('suppliers/create', [ContactDirectoryController::class, 'createSupplier'])->name('suppliers.create');
        Route::post('suppliers', [ContactDirectoryController::class, 'storeSupplier'])->name('suppliers.store');
        Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');
        Route::get('alerts', [AlertsController::class, 'index'])->name('alerts.index');

        Route::prefix('catalog')->name('catalog.')->controller(CatalogController::class)->group(function () {
            Route::get('car-models', 'carModels')->name('car-models.index');
            Route::get('car-models/create', 'createCarModel')->name('car-models.create');
            Route::post('car-models', 'storeCarModel')->name('car-models.store');

            Route::get('brands', 'brands')->name('brands.index');
            Route::get('brands/create', 'createBrand')->name('brands.create');
            Route::post('brands', 'storeBrand')->name('brands.store');

            Route::get('part-types', 'partTypes')->name('part-types.index');
            Route::get('part-types/create', 'createPartType')->name('part-types.create');
            Route::post('part-types', 'storePartType')->name('part-types.store');

            Route::get('fuel-types', 'fuelTypes')->name('fuel-types.index');
            Route::get('fuel-types/create', 'createFuelType')->name('fuel-types.create');
            Route::post('fuel-types', 'storeFuelType')->name('fuel-types.store');

            Route::get('products', 'products')->name('products.index');
            Route::get('products/create', 'createProduct')->name('products.create');
            Route::post('products', 'storeProduct')->name('products.store');
        });

        Route::get('settings', [AdminSettingsController::class, 'index'])->name('settings.index');
        Route::post('settings', [AdminSettingsController::class, 'update'])->name('settings.update');

        Route::resource('payment-accounts', PaymentAccountController::class)
            ->parameters(['payment-accounts' => 'payment_account']);
    });
