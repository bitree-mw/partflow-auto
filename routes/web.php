<?php

use App\Http\Controllers\Web\PaymentAccountController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('pos');
});

Route::get('/pos', function () {
    return view('pos');
});

// Web back-office routes are kept separate from API routes and can receive auth/role middleware as the session UI grows.
Route::prefix('back-office')
    ->name('web.')
    ->group(function () {
        Route::resource('payment-accounts', PaymentAccountController::class)
            ->parameters(['payment-accounts' => 'payment_account']);
    });
