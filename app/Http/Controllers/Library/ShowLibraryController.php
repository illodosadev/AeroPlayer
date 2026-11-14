<?php

namespace App\Http\Controllers\Library;

use App\Application\Library\Queries\GetLibrary;
use App\Application\Library\Results\PlaylistResult;
use App\Application\UseCaseFailed;
use App\Bus\QueryBus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Library\Concerns\MapsLibraryErrors;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The library page (spotify-library-overview, design D4). Always status 200: a Spotify failure becomes the "error"
 * prop, never an error page, and carries no Spotify body or token.
 */
class ShowLibraryController extends Controller
{
    use MapsLibraryErrors;

    public function __construct(private readonly QueryBus $queries) {}

    public function __invoke(): Response
    {
        try {
            $library = $this->queries->ask(new GetLibrary);
        } catch (UseCaseFailed $failure) {
            return Inertia::render('Library', [
                'playlists' => null,
                'playlistsAllowed' => false,
                'likedTotal' => null,
                'likedAllowed' => false,
                'error' => $this->libraryError($failure),
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
}
