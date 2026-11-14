<?php

use App\Http\Controllers\SpotifyConnectionController;
use App\Http\Controllers\SpotifySettingsController;
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

// Spotify connection and settings (spotify-connection, design D7).
Route::middleware('auth')->group(function () {
    Route::get('spotify/connect', [SpotifyConnectionController::class, 'redirect'])->name('spotify.connect');
    Route::get('spotify/callback', [SpotifyConnectionController::class, 'callback'])->name('spotify.callback');
    Route::delete('spotify/connection', [SpotifyConnectionController::class, 'destroy'])->name('spotify.disconnect');

    Route::redirect('settings', '/settings/spotify')->name('settings');
    Route::get('settings/spotify', [SpotifySettingsController::class, 'show'])->name('spotify.settings');
});

require __DIR__.'/auth.php';
