<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CarModelController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FuelTypeController;
use App\Http\Controllers\Api\InventoryDocumentController;
use App\Http\Controllers\Api\PartTypeController;
use App\Http\Controllers\Api\PaymentAccountController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PosProductController;
use App\Http\Controllers\Api\ProductController;
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

    Route::prefix('reports')->controller(ReportController::class)->group(function () {
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
        Route::get('customer-balances', 'customerBalances');
        Route::get('payments-by-account', 'paymentsByAccount');
        Route::get('stock-movement-history', 'stockMovementHistory');
        Route::get('stock-transfer-history', 'stockTransferHistory');
        Route::get('stock-take-variance', 'stockTakeVariance');
        Route::get('expenses', 'expenses');
        Route::get('export', 'export');
    });

    Route::get('pos/products', [PosProductController::class, 'index']);
    Route::post('pos/sales', [SaleController::class, 'store']);

    Route::get('inventory-documents', [InventoryDocumentController::class, 'index']);
    Route::get('inventory-documents/{inventoryDocument}', [InventoryDocumentController::class, 'show']);

    Route::get('stock-movements', [StockMovementController::class, 'index']);
    Route::get('stock-movements/{stockMovement}', [StockMovementController::class, 'show']);

    Route::get('purchases', [PurchaseController::class, 'index']);
    Route::post('purchases', [PurchaseController::class, 'store']);
    Route::get('purchases/{inventoryDocument}', [PurchaseController::class, 'show']);

    Route::get('sales', [SaleController::class, 'index']);
    Route::post('sales', [SaleController::class, 'store']);
    Route::get('sales/{inventoryDocument}', [SaleController::class, 'show']);

    Route::get('transfers', [TransferController::class, 'index']);
    Route::post('transfers', [TransferController::class, 'store']);
    Route::get('transfers/{inventoryDocument}', [TransferController::class, 'show']);

    Route::get('stock-adjustments', [StockAdjustmentController::class, 'index']);
    Route::post('stock-adjustments', [StockAdjustmentController::class, 'store']);
    Route::get('stock-adjustments/{inventoryDocument}', [StockAdjustmentController::class, 'show']);

    Route::get('stock-takes', [StockTakeController::class, 'index']);
    Route::post('stock-takes', [StockTakeController::class, 'store']);
    Route::get('stock-takes/{inventoryDocument}', [StockTakeController::class, 'show']);

    Route::get('sale-returns', [SaleReturnController::class, 'index']);
    Route::post('sale-returns', [SaleReturnController::class, 'store']);
    Route::get('sale-returns/{inventoryDocument}', [SaleReturnController::class, 'show']);

    Route::get('purchase-returns', [PurchaseReturnController::class, 'index']);
    Route::post('purchase-returns', [PurchaseReturnController::class, 'store']);
    Route::get('purchase-returns/{inventoryDocument}', [PurchaseReturnController::class, 'show']);

    Route::apiResource('payment-accounts', PaymentAccountController::class)
        ->parameters(['payment-accounts' => 'payment_account']);

    Route::apiResource('payments', PaymentController::class)
        ->only(['index', 'store', 'show', 'destroy']);

    Route::apiResource('expense-categories', ExpenseCategoryController::class)
        ->parameters(['expense-categories' => 'expense_category']);

    Route::apiResource('expenses', ExpenseController::class);

    Route::apiResource('site-stocks', SiteStockController::class);

    Route::apiResource('products', ProductController::class);

    Route::apiResource('tax-profiles', TaxProfileController::class)
        ->parameters(['tax-profiles' => 'tax_profile']);

    Route::apiResource('brands', BrandController::class);

    Route::apiResource('fuel-types', FuelTypeController::class)
        ->parameters(['fuel-types' => 'fuel_type']);

    Route::apiResource('part-types', PartTypeController::class)
        ->parameters(['part-types' => 'part_type']);

    Route::apiResource('contacts', ContactController::class);

    Route::apiResource('car-models', CarModelController::class)
        ->parameters(['car-models' => 'car_model']);

    Route::apiResource('user-site-accesses', UserSiteAccessController::class)
        ->parameters(['user-site-accesses' => 'user_site_access']);

    Route::apiResource('roles', RoleController::class);
    Route::apiResource('sites', SiteController::class);

});
