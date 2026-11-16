<?php

use App\Http\Controllers\Library\RefreshLibraryController;
use App\Http\Controllers\Library\ShowLibraryController;
use App\Http\Controllers\Library\ShowLikedSongsController;
use App\Http\Controllers\Library\ShowLikedSongsPageController;
use App\Http\Controllers\Library\ShowPlaylistController;
use App\Http\Controllers\Library\ShowPlaylistSongsPageController;
use App\Http\Controllers\PlaybackQueue\AddQueueItemsController;
use App\Http\Controllers\PlaybackQueue\ChangeQueueCurrentController;
use App\Http\Controllers\PlaybackQueue\MoveQueueItemController;
use App\Http\Controllers\PlaybackQueue\RemoveQueueItemController;
use App\Http\Controllers\PlaybackQueue\ReplaceQueueController;
use App\Http\Controllers\PlaybackQueue\ShowQueueController;
use App\Http\Controllers\RedirectHomeController;
use App\Http\Controllers\Search\ShowSearchController;
use App\Http\Controllers\Search\ShowSearchResultsController;
use App\Http\Controllers\ShowDashboardController;
use App\Http\Controllers\ShowStyleguideController;
use App\Http\Controllers\Spotify\DisconnectSpotifyController;
use App\Http\Controllers\Spotify\FinishSpotifyConnectionController;
use App\Http\Controllers\Spotify\ShowSpotifyPlaybackTokenController;
use App\Http\Controllers\Spotify\ShowSpotifySettingsController;
use App\Http\Controllers\Spotify\StartSpotifyConnectionController;
use App\Http\Controllers\Spotify\StartSpotifyPlaybackController;
use App\Http\Controllers\YouTubeMusic\DisconnectYouTubeMusicController;
use App\Http\Controllers\YouTubeMusic\ShowYouTubeMusicConnectStatusController;
use App\Http\Controllers\YouTubeMusic\ShowYouTubeMusicSettingsController;
use App\Http\Controllers\YouTubeMusic\StartYouTubeMusicConnectionController;
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

    // The search page (spotify-search-page, design D1) and its results in the app's common format
    // (spotify-search-api, design D4).
    Route::get('search', ShowSearchController::class)->name('search');

    // The library overview and its "Refresh" (spotify-library-overview, design D3 and D4).
    Route::get('library', ShowLibraryController::class)->name('library');
    Route::post('library/refresh', RefreshLibraryController::class)->name('library.refresh');

    // A playlist's songs and the saved songs, with "Load more" in JSON (spotify-library-tracks, design D4).
    Route::get('library/liked', ShowLikedSongsController::class)->name('library.liked');
    Route::get('library/liked/songs', ShowLikedSongsPageController::class)->name('library.liked.songs');
    Route::get('library/playlists/spotify/{id}', ShowPlaylistController::class)
        ->where('id', '[A-Za-z0-9]{1,64}')
        ->name('library.playlist');
    Route::get('library/playlists/spotify/{id}/songs', ShowPlaylistSongsPageController::class)
        ->where('id', '[A-Za-z0-9]{1,64}')
        ->name('library.playlist.songs');

    Route::get('search/results', ShowSearchResultsController::class)->name('search.results');

    // The owner's playback queue, in JSON (playback-queue, design D6).
    Route::get('queue', ShowQueueController::class)->name('queue.show');
    Route::put('queue', ReplaceQueueController::class)->name('queue.replace');
    Route::patch('queue', ChangeQueueCurrentController::class)->name('queue.update');
    Route::post('queue/items', AddQueueItemsController::class)->name('queue.items.add');
    Route::patch('queue/items/{item}', MoveQueueItemController::class)->whereNumber('item')->name('queue.items.move');
    Route::delete('queue/items/{item}', RemoveQueueItemController::class)->whereNumber('item')->name('queue.items.remove');

    // YouTube Music connection and settings (youtube-music-connection, design D7).
    Route::get('settings/youtube-music', ShowYouTubeMusicSettingsController::class)->name('youtube-music.settings');
    Route::post('youtube-music/connect', StartYouTubeMusicConnectionController::class)->name('youtube-music.connect');
    Route::get('youtube-music/connect/status', ShowYouTubeMusicConnectStatusController::class)
        ->name('youtube-music.connect.status');
    Route::delete('youtube-music/connection', DisconnectYouTubeMusicController::class)->name('youtube-music.disconnect');
});

require __DIR__.'/auth.php';
