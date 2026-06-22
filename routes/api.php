<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CarModelController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\FuelTypeController;
use App\Http\Controllers\Api\PartTypeController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SiteController;
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
