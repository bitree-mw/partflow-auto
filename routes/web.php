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
    Route::get('/pos', [PosController::class, 'index'])->middleware('permission:sales.create')->name('web.pos');
    Route::get('/pos/products.json', [PosController::class, 'productsJson'])->middleware('permission:sales.create')->name('web.pos.products');
    Route::get('/pos/suggestions.json', [PosController::class, 'suggestionsJson'])->middleware('permission:sales.create')->name('web.pos.suggestions');
    Route::get('/pos/vehicle-models.json', [PosController::class, 'vehicleModelsJson'])->middleware('permission:sales.create')->name('web.pos.vehicle-models');
    Route::get('/pos/product-types.json', [PosController::class, 'productTypesJson'])->middleware('permission:sales.create')->name('web.pos.product-types');
    Route::post('/pos/site', [PosController::class, 'updateSite'])->name('web.pos.site');
    Route::post('/pos/sales', [PosController::class, 'store'])->middleware('permission:sales.create')->name('web.pos.sales');
});

// Web back-office routes are kept separate from API routes and receive auth/role middleware as the session UI grows.
Route::prefix('back-office')
    ->name('web.')
    ->middleware('auth')
    ->group(function () {
        Route::get('/', fn () => redirect()->route('web.dashboard'));
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard/live.json', [DashboardController::class, 'live'])->name('dashboard.live');
        Route::get('sales', [SalesController::class, 'index'])->middleware('permission:sales.view')->name('sales.index');
        Route::get('sales/{inventoryDocument}/edit', [SalesController::class, 'edit'])->middleware('permission:sales.manage')->name('sales.edit');
        Route::put('sales/{inventoryDocument}', [SalesController::class, 'update'])->middleware('permission:sales.manage')->name('sales.update');
        Route::delete('sales/{inventoryDocument}/payments/{payment}', [SalesController::class, 'destroyPayment'])->middleware('permission:sales.manage')->name('sales.payments.destroy');
        Route::get('purchases', [PurchasesController::class, 'index'])->middleware('permission:purchases.view')->name('purchases.index');
        Route::get('purchases/create', [PurchasesController::class, 'create'])->middleware('permission:purchases.create')->name('purchases.create');
        Route::post('purchases', [PurchasesController::class, 'store'])->middleware('permission:purchases.create')->name('purchases.store');
        Route::get('purchases/{inventoryDocument}/edit', [PurchasesController::class, 'edit'])->middleware('permission:purchases.manage')->name('purchases.edit');
        Route::put('purchases/{inventoryDocument}', [PurchasesController::class, 'update'])->middleware('permission:purchases.manage')->name('purchases.update');
        Route::delete('purchases/{inventoryDocument}/payments/{payment}', [PurchasesController::class, 'destroyPayment'])->middleware('permission:purchases.manage')->name('purchases.payments.destroy');
        Route::get('customers', [ContactDirectoryController::class, 'customers'])->middleware('permission:customers.view')->name('customers.index');
        Route::get('customers/create', [ContactDirectoryController::class, 'createCustomer'])->middleware('permission:customers.manage')->name('customers.create');
        Route::post('customers', [ContactDirectoryController::class, 'storeCustomer'])->middleware('permission:customers.manage')->name('customers.store');
        Route::delete('customers/{contact}', [ContactDirectoryController::class, 'destroyCustomer'])->middleware('permission:customers.manage')->name('customers.destroy');
        Route::get('suppliers', [ContactDirectoryController::class, 'suppliers'])->middleware('permission:suppliers.view')->name('suppliers.index');
        Route::get('suppliers/create', [ContactDirectoryController::class, 'createSupplier'])->middleware('permission:suppliers.manage')->name('suppliers.create');
        Route::post('suppliers', [ContactDirectoryController::class, 'storeSupplier'])->middleware('permission:suppliers.manage')->name('suppliers.store');
        Route::delete('suppliers/{contact}', [ContactDirectoryController::class, 'destroySupplier'])->middleware('permission:suppliers.manage')->name('suppliers.destroy');
        Route::get('reports', [ReportsController::class, 'index'])->middleware('permission:reports.view')->name('reports.index');
        Route::get('reports/view', [ReportsController::class, 'viewReport'])->middleware('permission:reports.view')->name('reports.view');
        Route::get('reports/export', [ReportsController::class, 'export'])->middleware('permission:reports.view')->name('reports.export');
        Route::get('alerts', [AlertsController::class, 'index'])->name('alerts.index');

        Route::prefix('catalog')->name('catalog.')->controller(CatalogController::class)->group(function () {
            Route::get('sites', 'siteManagement')->middleware('permission:stock.view')->name('sites.index');
            Route::get('sites/create', 'createSite')->middleware('permission:settings.manage')->name('sites.create');
            Route::post('sites', 'storeSite')->middleware('permission:settings.manage')->name('sites.store');
            Route::get('sites/transfers', 'siteTransfers')->middleware('permission:stock.view')->name('sites.transfers.index');
            Route::get('sites/transfers/create', 'createSiteTransfer')->middleware('permission:stock.transfer')->name('sites.transfers.create');
            Route::post('sites/transfers', 'storeSiteTransfer')->middleware('permission:stock.transfer')->name('sites.transfers.store');
            Route::get('sites/transfers/{inventoryDocument}', 'showSiteTransfer')->middleware('permission:stock.view')->name('sites.transfers.show');
            Route::get('sites/stock-takes', 'siteStockTakes')->middleware('permission:stock.view')->name('sites.stock-takes.index');
            Route::get('sites/stock-takes/create', 'createSiteStockTake')->middleware('permission:stock.adjust')->name('sites.stock-takes.create');
            Route::post('sites/stock-takes', 'storeSiteStockTake')->middleware('permission:stock.adjust')->name('sites.stock-takes.store');
            Route::get('sites/stock-takes/{inventoryDocument}', 'showSiteStockTake')->middleware('permission:stock.view')->name('sites.stock-takes.show');

            Route::get('car-models', 'carModels')->middleware('permission:catalogue.view')->name('car-models.index');
            Route::get('car-models/create', 'createCarModel')->middleware('permission:catalogue.manage')->name('car-models.create');
            Route::post('car-models', 'storeCarModel')->middleware('permission:catalogue.manage')->name('car-models.store');
            Route::get('car-models/{car_model}/edit', 'editCarModel')->middleware('permission:catalogue.manage')->name('car-models.edit');
            Route::put('car-models/{car_model}', 'updateCarModel')->middleware('permission:catalogue.manage')->name('car-models.update');
            Route::delete('car-models/{car_model}', 'destroyCarModel')->middleware('permission:catalogue.manage')->name('car-models.destroy');

            Route::get('brands', 'brands')->middleware('permission:catalogue.view')->name('brands.index');
            Route::get('brands/create', 'createBrand')->middleware('permission:catalogue.manage')->name('brands.create');
            Route::post('brands', 'storeBrand')->middleware('permission:catalogue.manage')->name('brands.store');
            Route::get('brands/{brand}/edit', 'editBrand')->middleware('permission:catalogue.manage')->name('brands.edit');
            Route::put('brands/{brand}', 'updateBrand')->middleware('permission:catalogue.manage')->name('brands.update');
            Route::delete('brands/{brand}', 'destroyBrand')->middleware('permission:catalogue.manage')->name('brands.destroy');

            Route::get('product-types', 'productTypes')->middleware('permission:catalogue.view')->name('product-types.index');
            Route::get('product-types/create', 'createProductType')->middleware('permission:catalogue.manage')->name('product-types.create');
            Route::post('product-types', 'storeProductType')->middleware('permission:catalogue.manage')->name('product-types.store');
            Route::get('product-types/{product_type}/edit', 'editProductType')->middleware('permission:catalogue.manage')->name('product-types.edit');
            Route::put('product-types/{product_type}', 'updateProductType')->middleware('permission:catalogue.manage')->name('product-types.update');
            Route::delete('product-types/{product_type}', 'destroyProductType')->middleware('permission:catalogue.manage')->name('product-types.destroy');

            Route::get('fuel-types', 'fuelTypes')->middleware('permission:catalogue.view')->name('fuel-types.index');
            Route::get('fuel-types/create', 'createFuelType')->middleware('permission:catalogue.manage')->name('fuel-types.create');
            Route::post('fuel-types', 'storeFuelType')->middleware('permission:catalogue.manage')->name('fuel-types.store');
            Route::get('fuel-types/{fuel_type}/edit', 'editFuelType')->middleware('permission:catalogue.manage')->name('fuel-types.edit');
            Route::put('fuel-types/{fuel_type}', 'updateFuelType')->middleware('permission:catalogue.manage')->name('fuel-types.update');
            Route::delete('fuel-types/{fuel_type}', 'destroyFuelType')->middleware('permission:catalogue.manage')->name('fuel-types.destroy');

            Route::get('car-model-options', 'carModelOptionsSearch')->middleware('permission:catalogue.view')->name('car-model-options');
            Route::get('product-type-options', 'productTypeOptionsSearch')->middleware('permission:catalogue.view')->name('product-type-options');
            Route::get('products', 'products')->middleware('permission:catalogue.view')->name('products.index');
            Route::get('products/create', 'createProduct')->middleware('permission:catalogue.manage')->name('products.create');
            Route::post('products', 'storeProduct')->middleware('permission:catalogue.manage')->name('products.store');
            Route::get('products/{product}/edit', 'editProduct')->middleware('permission:catalogue.manage')->name('products.edit');
            Route::put('products/{product}', 'updateProduct')->middleware('permission:catalogue.manage')->name('products.update');
            Route::delete('products/{product}', 'destroyProduct')->middleware('permission:catalogue.manage')->name('products.destroy');
        });

        Route::get('settings', [AdminSettingsController::class, 'index'])->middleware('permission:settings.manage')->name('settings.index');
        Route::post('settings', [AdminSettingsController::class, 'update'])->middleware('permission:settings.manage')->name('settings.update');
        Route::post('settings/business-information', [AdminSettingsController::class, 'saveBusinessSettings'])->middleware('permission:settings.manage')->name('settings.business-information.update');
        Route::get('settings/vehicle-makes/{carMake}', [AdminSettingsController::class, 'showVehicleMake'])->middleware('permission:settings.manage')->name('settings.vehicle-makes.show');
        Route::post('settings/vehicle-makes/{carMake}/models', [AdminSettingsController::class, 'storeVehicleModelForMake'])->middleware('permission:settings.manage')->name('settings.vehicle-makes.models.store');
        Route::put('settings/vehicle-models/{vehicleModel}', [AdminSettingsController::class, 'updateVehicleModelForMake'])->middleware('permission:settings.manage')->name('settings.vehicle-models.update');
        Route::delete('settings/vehicle-models/{vehicleModel}', [AdminSettingsController::class, 'deactivateVehicleModelForMake'])->middleware('permission:settings.manage')->name('settings.vehicle-models.destroy');

        Route::resource('payment-accounts', PaymentAccountController::class)
            ->parameters(['payment-accounts' => 'payment_account'])
            ->middleware('permission:payment-accounts.manage');
    });
