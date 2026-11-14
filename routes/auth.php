<?php

use App\Http\Controllers\Auth\LogInController;
use App\Http\Controllers\Auth\LogOutController;
use App\Http\Controllers\Auth\ShowLoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', ShowLoginController::class)
        ->name('login');

    Route::post('login', LogInController::class);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', LogOutController::class)
        ->name('logout');
});
