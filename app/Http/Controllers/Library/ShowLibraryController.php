<?php

namespace App\Http\Controllers\Library;

use App\Application\Library\Queries\GetLibrary;
use App\Application\Library\Results\PlaylistResult;
use App\Application\Spotify\Exceptions\SpotifyApiFailed;
use App\Application\Spotify\Exceptions\SpotifyNotConnected;
use App\Application\Spotify\Exceptions\SpotifyRateLimited;
use App\Application\Spotify\Exceptions\SpotifyReconnectRequired;
use App\Bus\QueryBus;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The library page (spotify-library-overview, design D4). Always status 200: a Spotify failure becomes the "error"
 * prop, never an error page, and carries no Spotify body or token.
 */
class ShowLibraryController extends Controller
{
    public function __construct(private readonly QueryBus $queries) {}

    public function __invoke(): Response
    {
        try {
            $library = $this->queries->ask(new GetLibrary);
        } catch (SpotifyApiFailed $failure) {
            return Inertia::render('Library', [
                'playlists' => null,
                'playlistsAllowed' => false,
                'likedTotal' => null,
                'likedAllowed' => false,
                'error' => $this->errorCode($failure),
            ]);
        }

        return Inertia::render('Library', [
            'playlists' => $library->playlists === null
                ? null
                : array_map(fn (PlaylistResult $playlist) => $playlist->toArray(), $library->playlists),
            'playlistsAllowed' => $library->playlistsAllowed,
            'likedTotal' => $library->likedTotal,
            'likedAllowed' => $library->likedAllowed,
            'error' => null,
        ]);
    }

    private function errorCode(SpotifyApiFailed $failure): string
    {
        return match (true) {
            $failure instanceof SpotifyNotConnected => 'spotify_not_connected',
            $failure instanceof SpotifyReconnectRequired => 'spotify_reconnect_required',
            $failure instanceof SpotifyRateLimited => 'rate_limited',
            default => 'spotify_unavailable',
        };
    }
}
