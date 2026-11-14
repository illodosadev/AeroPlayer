<?php

use App\Http\Controllers\RedirectHomeController;
use App\Http\Controllers\ShowDashboardController;
use App\Http\Controllers\ShowStyleguideController;
use App\Http\Controllers\Spotify\DisconnectSpotifyController;
use App\Http\Controllers\Spotify\FinishSpotifyConnectionController;
use App\Http\Controllers\Spotify\ShowSpotifyPlaybackTokenController;
use App\Http\Controllers\Spotify\ShowSpotifySettingsController;
use App\Http\Controllers\Spotify\StartSpotifyConnectionController;
use App\Http\Controllers\Spotify\StartSpotifyPlaybackController;
use Illuminate\Support\Facades\Route;

// One invokable controller per route (single-action-controllers, design D4).
Route::get('/', RedirectHomeController::class)->name('home');

Route::get('dashboard', ShowDashboardController::class)->middleware('auth')->name('dashboard');

Route::get('styleguide', ShowStyleguideController::class)->middleware('auth')->name('styleguide');

// Spotify connection and settings (spotify-connection, design D7).
Route::middleware('auth')->group(function () {
    Route::get('spotify/connect', StartSpotifyConnectionController::class)->name('spotify.connect');
    Route::get('spotify/callback', FinishSpotifyConnectionController::class)->name('spotify.callback');
    Route::delete('spotify/connection', DisconnectSpotifyController::class)->name('spotify.disconnect');

    Route::redirect('settings', '/settings/spotify')->name('settings');
    Route::get('settings/spotify', ShowSpotifySettingsController::class)->name('spotify.settings');

    // The browser player's endpoints (spotify-playback-api, design D1).
    Route::get('spotify/token', ShowSpotifyPlaybackTokenController::class)->name('spotify.token');
    Route::put('spotify/player/play', StartSpotifyPlaybackController::class)->name('spotify.player.play');
});

require __DIR__.'/auth.php';
