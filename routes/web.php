<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('pos');
});

Route::get('/pos', function () {
    return view('pos');
});
