<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect(auth()->check() ? route('dashboard') : route('login'));
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware('auth')->name('dashboard');

Route::get('styleguide', function () {
    return Inertia::render('Styleguide');
})->middleware('auth')->name('styleguide');

require __DIR__.'/auth.php';
