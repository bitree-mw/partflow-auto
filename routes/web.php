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
        Route::get('sales/{inventoryDocument}/edit', [SalesController::class, 'edit'])->name('sales.edit');
        Route::put('sales/{inventoryDocument}', [SalesController::class, 'update'])->name('sales.update');
        Route::delete('sales/{inventoryDocument}/payments/{payment}', [SalesController::class, 'destroyPayment'])->name('sales.payments.destroy');
        Route::get('purchases', [PurchasesController::class, 'index'])->name('purchases.index');
        Route::get('purchases/create', [PurchasesController::class, 'create'])->name('purchases.create');
        Route::post('purchases', [PurchasesController::class, 'store'])->name('purchases.store');
        Route::get('purchases/{inventoryDocument}/edit', [PurchasesController::class, 'edit'])->name('purchases.edit');
        Route::put('purchases/{inventoryDocument}', [PurchasesController::class, 'update'])->name('purchases.update');
        Route::delete('purchases/{inventoryDocument}/payments/{payment}', [PurchasesController::class, 'destroyPayment'])->name('purchases.payments.destroy');
        Route::get('customers', [ContactDirectoryController::class, 'customers'])->name('customers.index');
        Route::get('customers/create', [ContactDirectoryController::class, 'createCustomer'])->name('customers.create');
        Route::post('customers', [ContactDirectoryController::class, 'storeCustomer'])->name('customers.store');
        Route::delete('customers/{contact}', [ContactDirectoryController::class, 'destroyCustomer'])->name('customers.destroy');
        Route::get('suppliers', [ContactDirectoryController::class, 'suppliers'])->name('suppliers.index');
        Route::get('suppliers/create', [ContactDirectoryController::class, 'createSupplier'])->name('suppliers.create');
        Route::post('suppliers', [ContactDirectoryController::class, 'storeSupplier'])->name('suppliers.store');
        Route::delete('suppliers/{contact}', [ContactDirectoryController::class, 'destroySupplier'])->name('suppliers.destroy');
        Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');
        Route::get('alerts', [AlertsController::class, 'index'])->name('alerts.index');

        Route::prefix('catalog')->name('catalog.')->controller(CatalogController::class)->group(function () {
            Route::get('sites', 'siteManagement')->name('sites.index');
            Route::get('sites/create', 'createSite')->name('sites.create');
            Route::post('sites', 'storeSite')->name('sites.store');
            Route::get('sites/transfers', 'siteTransfers')->name('sites.transfers.index');
            Route::get('sites/transfers/create', 'createSiteTransfer')->name('sites.transfers.create');
            Route::post('sites/transfers', 'storeSiteTransfer')->name('sites.transfers.store');
            Route::get('sites/transfers/{inventoryDocument}', 'showSiteTransfer')->name('sites.transfers.show');
            Route::get('sites/stock-takes', 'siteStockTakes')->name('sites.stock-takes.index');
            Route::get('sites/stock-takes/create', 'createSiteStockTake')->name('sites.stock-takes.create');
            Route::post('sites/stock-takes', 'storeSiteStockTake')->name('sites.stock-takes.store');
            Route::get('sites/stock-takes/{inventoryDocument}', 'showSiteStockTake')->name('sites.stock-takes.show');

            Route::get('car-models', 'carModels')->name('car-models.index');
            Route::get('car-models/create', 'createCarModel')->name('car-models.create');
            Route::post('car-models', 'storeCarModel')->name('car-models.store');
            Route::get('car-models/{car_model}/edit', 'editCarModel')->name('car-models.edit');
            Route::put('car-models/{car_model}', 'updateCarModel')->name('car-models.update');
            Route::delete('car-models/{car_model}', 'destroyCarModel')->name('car-models.destroy');

            Route::get('brands', 'brands')->name('brands.index');
            Route::get('brands/create', 'createBrand')->name('brands.create');
            Route::post('brands', 'storeBrand')->name('brands.store');
            Route::get('brands/{brand}/edit', 'editBrand')->name('brands.edit');
            Route::put('brands/{brand}', 'updateBrand')->name('brands.update');
            Route::delete('brands/{brand}', 'destroyBrand')->name('brands.destroy');

            Route::get('part-types', 'partTypes')->name('part-types.index');
            Route::get('part-types/create', 'createPartType')->name('part-types.create');
            Route::post('part-types', 'storePartType')->name('part-types.store');
            Route::get('part-types/{part_type}/edit', 'editPartType')->name('part-types.edit');
            Route::put('part-types/{part_type}', 'updatePartType')->name('part-types.update');
            Route::delete('part-types/{part_type}', 'destroyPartType')->name('part-types.destroy');

            Route::get('fuel-types', 'fuelTypes')->name('fuel-types.index');
            Route::get('fuel-types/create', 'createFuelType')->name('fuel-types.create');
            Route::post('fuel-types', 'storeFuelType')->name('fuel-types.store');
            Route::get('fuel-types/{fuel_type}/edit', 'editFuelType')->name('fuel-types.edit');
            Route::put('fuel-types/{fuel_type}', 'updateFuelType')->name('fuel-types.update');
            Route::delete('fuel-types/{fuel_type}', 'destroyFuelType')->name('fuel-types.destroy');

            Route::get('car-model-options', 'carModelOptionsSearch')->name('car-model-options');
            Route::get('products', 'products')->name('products.index');
            Route::get('products/create', 'createProduct')->name('products.create');
            Route::post('products', 'storeProduct')->name('products.store');
            Route::get('products/{product}/edit', 'editProduct')->name('products.edit');
            Route::put('products/{product}', 'updateProduct')->name('products.update');
            Route::delete('products/{product}', 'destroyProduct')->name('products.destroy');
        });

        Route::get('settings', [AdminSettingsController::class, 'index'])->name('settings.index');
        Route::post('settings', [AdminSettingsController::class, 'update'])->name('settings.update');
        Route::get('settings/vehicle-makes/{carMake}', [AdminSettingsController::class, 'showVehicleMake'])->name('settings.vehicle-makes.show');
        Route::post('settings/vehicle-makes/{carMake}/models', [AdminSettingsController::class, 'storeVehicleModelForMake'])->name('settings.vehicle-makes.models.store');
        Route::put('settings/vehicle-models/{vehicleModel}', [AdminSettingsController::class, 'updateVehicleModelForMake'])->name('settings.vehicle-models.update');
        Route::delete('settings/vehicle-models/{vehicleModel}', [AdminSettingsController::class, 'deactivateVehicleModelForMake'])->name('settings.vehicle-models.destroy');

        Route::resource('payment-accounts', PaymentAccountController::class)
            ->parameters(['payment-accounts' => 'payment_account']);
    });
