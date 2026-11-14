<?php

namespace App\Http\Controllers\Library;

use App\Application\Library\Queries\GetLikedSongs;
use App\Application\UseCaseFailed;
use App\Bus\QueryBus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Library\Concerns\MapsLibraryErrors;
use App\Http\Paging\LibraryCursor;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Liked Songs page (spotify-library-tracks, design D4): the first 50 saved songs and Spotify's count. Always
 * status 200.
 */
class ShowLikedSongsController extends Controller
{
    use MapsLibraryErrors;

    public function __construct(private readonly QueryBus $queries) {}

    public function __invoke(): Response
    {
        try {
            $page = $this->queries->ask(new GetLikedSongs);
        } catch (UseCaseFailed $failure) {
            return Inertia::render('library/Liked', [
                'allowed' => false,
                'songs' => null,
                'total' => null,
                'nextCursor' => null,
                'error' => $this->libraryError($failure),
            ]);
        }

        $songs = $page->songs;
        $nextOffset = $songs?->nextOffset;

        return Inertia::render('library/Liked', [
            'allowed' => $page->allowed,
            'songs' => $songs?->itemsArray(),
            'total' => $songs?->total,
            'nextCursor' => $nextOffset === null ? null : LibraryCursor::encode(LibraryCursor::LIKED_SCOPE, $nextOffset),
            'error' => null,
        ]);
    }
}
