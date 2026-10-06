<?php

use App\Http\Controllers\SuperAdmin\AuditLogController;
use App\Http\Controllers\SuperAdmin\AuthController;
use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\EmailController;
use App\Http\Controllers\SuperAdmin\PackageController;
use App\Http\Controllers\SuperAdmin\SiteController;
use App\Http\Controllers\SuperAdmin\UserController;
use Illuminate\Support\Facades\Route;

// Super admin console: password-only session flag, independent of staff accounts (see SuperAdminAuthService).
Route::prefix('suadmin')->name('suadmin.')->group(function () {
    Route::get('login', [AuthController::class, 'create'])->name('login');
    Route::post('login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');

    Route::middleware('suadmin')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::post('logout', [AuthController::class, 'destroy'])->name('logout');

        Route::get('sites', [SiteController::class, 'index'])->name('sites.index');
        Route::get('sites/create', [SiteController::class, 'create'])->name('sites.create');
        Route::post('sites', [SiteController::class, 'store'])->name('sites.store');
        Route::get('sites/{site}/edit', [SiteController::class, 'edit'])->name('sites.edit');
        Route::put('sites/{site}', [SiteController::class, 'update'])->name('sites.update');
        Route::patch('sites/{site}/status', [SiteController::class, 'status'])->name('sites.status');
        Route::delete('sites/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');

        Route::get('audit-logs', AuditLogController::class)->name('audit-logs.index');

        Route::get('email', [EmailController::class, 'edit'])->name('email.edit');
        Route::post('email/test', [EmailController::class, 'sendTest'])->middleware('throttle:6,1')->name('email.test');

        Route::get('package', [PackageController::class, 'edit'])->name('package.edit');
        Route::put('package', [PackageController::class, 'update'])->name('package.update');
        Route::put('package/support', [PackageController::class, 'updateSupport'])->name('package.support');
    });
});
