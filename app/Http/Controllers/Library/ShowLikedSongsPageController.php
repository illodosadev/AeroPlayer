<?php

namespace App\Http\Controllers\Library;

use App\Application\Library\Queries\GetLikedSongs;
use App\Application\Spotify\Exceptions\SpotifyApiFailed;
use App\Bus\QueryBus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Spotify\Concerns\RespondsWithSpotifyErrors;
use App\Http\Paging\LibraryCursor;
use App\Http\Requests\Library\LibrarySongsPageRequest;
use Illuminate\Http\JsonResponse;

/**
 * "Load more" on the Liked Songs page (spotify-library-tracks, design D4): the next saved songs and the cursor after
 * them, in JSON and never cached.
 */
class ShowLikedSongsPageController extends Controller
{
    use RespondsWithSpotifyErrors;

    public function __construct(private readonly QueryBus $queries) {}

    public function __invoke(LibrarySongsPageRequest $request): JsonResponse
    {
        try {
            $page = $this->queries->ask(new GetLikedSongs($request->offset()));
        } catch (SpotifyApiFailed $failure) {
            return $this->spotifyError($failure);
        }

        // The permission was withdrawn since the page loaded: nothing was sent to Spotify.
        if (! $page->allowed || $page->songs === null) {
            return response()->json(['error' => 'permission_missing'], 403, $this->noStore());
        }

        $nextOffset = $page->songs->nextOffset;

        return response()->json([
            'items' => $page->songs->itemsArray(),
            'next_cursor' => $nextOffset === null ? null : LibraryCursor::encode(LibraryCursor::LIKED_SCOPE, $nextOffset),
        ], 200, $this->noStore());
    }
}
