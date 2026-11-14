<?php

namespace App\Http\Controllers\Library\Concerns;

use App\Application\Library\Exceptions\PlaylistUnavailable;
use App\Application\Spotify\Exceptions\SpotifyNotConnected;
use App\Application\Spotify\Exceptions\SpotifyRateLimited;
use App\Application\Spotify\Exceptions\SpotifyReconnectRequired;
use App\Application\UseCaseFailed;

/**
 * The "error" prop of the library pages (spotify-library-overview, design D4; spotify-library-tracks, design D4). The
 * pages always answer 200 and carry only this code, never a Spotify body or token.
 */
trait MapsLibraryErrors
{
    protected function libraryError(UseCaseFailed $failure): string
    {
        return match (true) {
            $failure instanceof SpotifyNotConnected => 'spotify_not_connected',
            $failure instanceof SpotifyReconnectRequired => 'spotify_reconnect_required',
            $failure instanceof SpotifyRateLimited => 'rate_limited',
            $failure instanceof PlaylistUnavailable => 'playlist_unavailable',
            default => 'spotify_unavailable',
        };
    }
}
