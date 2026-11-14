<?php

namespace App\Http\Controllers\Library;

use App\Application\Library\Queries\GetPlaylist;
use App\Application\UseCaseFailed;
use App\Bus\QueryBus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Library\Concerns\MapsLibraryErrors;
use App\Http\Paging\LibraryCursor;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A playlist page (spotify-library-tracks, design D4): the playlist and its first 50 songs, or the reason they are
 * not shown. Always status 200.
 */
class ShowPlaylistController extends Controller
{
    use MapsLibraryErrors;

    public function __construct(private readonly QueryBus $queries) {}

    public function __invoke(string $id): Response
    {
        try {
            $page = $this->queries->ask(new GetPlaylist($id));
        } catch (UseCaseFailed $failure) {
            return Inertia::render('library/Playlist', [
                'playlist' => null,
                'songs' => null,
                'nextCursor' => null,
                'error' => $this->libraryError($failure),
            ]);
        }

        $songs = $page->songs;
        $nextOffset = $songs?->nextOffset;

        return Inertia::render('library/Playlist', [
            'playlist' => $page->playlist->toArray(),
            'songs' => $songs?->itemsArray(),
            'nextCursor' => $nextOffset === null ? null : LibraryCursor::encode(LibraryCursor::playlistScope($id), $nextOffset),
            'error' => null,
        ]);
    }
}
