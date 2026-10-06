<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CarModelController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FuelTypeController;
use App\Http\Controllers\Api\InventoryDocumentController;
use App\Http\Controllers\Api\PaymentAccountController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PosProductController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductTypeController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\PurchaseReturnController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SaleReturnController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\SiteStockController;
use App\Http\Controllers\Api\StockAdjustmentController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\StockTakeController;
use App\Http\Controllers\Api\TaxProfileController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\UserSiteAccessController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', 'me');
        Route::post('/logout', 'logout');
    });
});

Route::middleware('auth:sanctum')->group(function () {

    Route::get('dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('alerts', [AlertController::class, 'index']);
    Route::get('alerts/summary', [AlertController::class, 'summary']);

    Route::prefix('reports')->controller(ReportController::class)->middleware('permission:reports.view')->group(function () {
        Route::get('current-stock-by-site', 'currentStockBySite');
        Route::get('low-stock-by-site', 'lowStockBySite');
        Route::get('out-of-stock-products', 'outOfStockProducts');
        Route::get('stock-valuation', 'stockValuation');
        Route::get('most-selling-products', 'mostSellingProducts');
        Route::get('least-selling-products', 'leastSellingProducts');
        Route::get('sales-by-date-range', 'salesByDateRange');
        Route::get('purchases-by-date-range', 'purchasesByDateRange');
        Route::get('profit-by-product', 'profitByProduct');
        Route::get('profit-by-site', 'profitBySite');
        Route::get('customer-balances', 'customerBalances')->middleware('feature:customer_balances');
        Route::get('payments-by-account', 'paymentsByAccount');
        Route::get('stock-movement-history', 'stockMovementHistory');
        Route::get('stock-transfer-history', 'stockTransferHistory')->middleware('feature:stock_transfers');
        Route::get('stock-take-variance', 'stockTakeVariance');
        Route::get('expenses', 'expenses')->middleware('feature:expenses');
        Route::get('export', 'export')->middleware('feature:csv_exports');
    });

    Route::get('pos/products', [PosProductController::class, 'index'])->middleware('permission:sales.create');
    Route::get('pos/suggestions', [PosProductController::class, 'suggestions'])->middleware('permission:sales.create');
    Route::post('pos/sales', [SaleController::class, 'store'])->middleware('permission:sales.create');

    Route::get('inventory-documents', [InventoryDocumentController::class, 'index'])->middleware('permission:sales.view,purchases.view,stock.view');
    Route::get('inventory-documents/{inventoryDocument}', [InventoryDocumentController::class, 'show'])->middleware('permission:sales.view,purchases.view,stock.view');

    Route::get('stock-movements', [StockMovementController::class, 'index'])->middleware('permission:stock.view');
    Route::get('stock-movements/{stockMovement}', [StockMovementController::class, 'show'])->middleware('permission:stock.view');

    Route::get('purchases', [PurchaseController::class, 'index'])->middleware('permission:purchases.view');
    Route::post('purchases', [PurchaseController::class, 'store'])->middleware('permission:purchases.create');
    Route::get('purchases/{inventoryDocument}', [PurchaseController::class, 'show'])->middleware('permission:purchases.view');

    Route::get('sales', [SaleController::class, 'index'])->middleware('permission:sales.view');
    Route::post('sales', [SaleController::class, 'store'])->middleware('permission:sales.create');
    Route::get('sales/{inventoryDocument}', [SaleController::class, 'show'])->middleware('permission:sales.view');

    Route::get('transfers', [TransferController::class, 'index'])->middleware(['permission:stock.view', 'feature:stock_transfers']);
    Route::post('transfers', [TransferController::class, 'store'])->middleware(['permission:stock.transfer', 'feature:stock_transfers']);
    Route::get('transfers/{inventoryDocument}', [TransferController::class, 'show'])->middleware(['permission:stock.view', 'feature:stock_transfers']);

    Route::get('stock-adjustments', [StockAdjustmentController::class, 'index'])->middleware('permission:stock.view');
    Route::post('stock-adjustments', [StockAdjustmentController::class, 'store'])->middleware('permission:stock.adjust');
    Route::get('stock-adjustments/{inventoryDocument}', [StockAdjustmentController::class, 'show'])->middleware('permission:stock.view');

    Route::get('stock-takes', [StockTakeController::class, 'index'])->middleware('permission:stock.view');
    Route::post('stock-takes', [StockTakeController::class, 'store'])->middleware('permission:stock.adjust');
    Route::get('stock-takes/{inventoryDocument}', [StockTakeController::class, 'show'])->middleware('permission:stock.view');

    Route::get('sale-returns', [SaleReturnController::class, 'index'])->middleware('permission:sales.view');
    Route::post('sale-returns', [SaleReturnController::class, 'store'])->middleware('permission:sales.manage');
    Route::get('sale-returns/{inventoryDocument}', [SaleReturnController::class, 'show'])->middleware('permission:sales.view');

    Route::get('purchase-returns', [PurchaseReturnController::class, 'index'])->middleware('permission:purchases.view');
    Route::post('purchase-returns', [PurchaseReturnController::class, 'store'])->middleware('permission:purchases.manage');
    Route::get('purchase-returns/{inventoryDocument}', [PurchaseReturnController::class, 'show'])->middleware('permission:purchases.view');

    Route::apiResource('payment-accounts', PaymentAccountController::class)
        ->only(['index', 'show'])
        ->parameters(['payment-accounts' => 'payment_account'])
        ->middleware('permission:payment-accounts.view');

    Route::apiResource('payment-accounts', PaymentAccountController::class)
        ->only(['store', 'update', 'destroy'])
        ->parameters(['payment-accounts' => 'payment_account'])
        ->middleware('permission:payment-accounts.manage');

    Route::apiResource('payments', PaymentController::class)
        ->only(['index', 'store', 'show', 'destroy'])
        ->middleware('permission:sales.manage,purchases.manage');

    Route::apiResource('expense-categories', ExpenseCategoryController::class)
        ->parameters(['expense-categories' => 'expense_category'])
        ->middleware(['permission:purchases.manage', 'feature:expenses']);

    Route::apiResource('expenses', ExpenseController::class)->middleware(['permission:purchases.manage', 'feature:expenses']);

    Route::apiResource('site-stocks', SiteStockController::class)->only(['index', 'show'])->middleware('permission:stock.view');
    Route::apiResource('site-stocks', SiteStockController::class)->except(['index', 'show'])->middleware('permission:stock.adjust');

    Route::apiResource('products', ProductController::class)->only(['index', 'show'])->middleware('permission:catalogue.view,sales.create,purchases.create,stock.view');
    Route::apiResource('products', ProductController::class)->except(['index', 'show'])->middleware('permission:catalogue.manage');

    Route::apiResource('tax-profiles', TaxProfileController::class)
        ->parameters(['tax-profiles' => 'tax_profile'])
        ->middleware('permission:catalogue.manage');

    Route::apiResource('brands', BrandController::class)->only(['index', 'show'])->middleware('permission:catalogue.view');
    Route::apiResource('brands', BrandController::class)->except(['index', 'show'])->middleware('permission:catalogue.manage');

    Route::apiResource('fuel-types', FuelTypeController::class)
        ->parameters(['fuel-types' => 'fuel_type'])
        ->middleware('permission:catalogue.manage');

    Route::apiResource('product-types', ProductTypeController::class)
        ->parameters(['product-types' => 'product_type'])
        ->middleware('permission:catalogue.manage');

    Route::apiResource('contacts', ContactController::class)->only(['index', 'show'])->middleware('permission:customers.view,suppliers.view,sales.create,purchases.create');
    Route::apiResource('contacts', ContactController::class)->except(['index', 'show'])->middleware('permission:customers.manage,suppliers.manage');

    Route::apiResource('car-models', CarModelController::class)
        ->parameters(['car-models' => 'car_model'])
        ->middleware('permission:catalogue.manage');

    Route::apiResource('user-site-accesses', UserSiteAccessController::class)
        ->parameters(['user-site-accesses' => 'user_site_access'])
        ->middleware(['permission:settings.manage', 'feature:branch_access']);

    Route::apiResource('roles', RoleController::class)->middleware('permission:settings.manage');
    Route::apiResource('sites', SiteController::class)->only(['index', 'show'])->middleware('permission:stock.view,sales.create,purchases.create');

});
