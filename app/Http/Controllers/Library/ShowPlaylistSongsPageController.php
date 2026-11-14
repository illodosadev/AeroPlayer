<?php

namespace App\Http\Controllers\Library;

use App\Application\Library\Exceptions\PlaylistUnavailable;
use App\Application\Library\Queries\GetPlaylistSongs;
use App\Application\Spotify\Exceptions\SpotifyApiFailed;
use App\Bus\QueryBus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Spotify\Concerns\RespondsWithSpotifyErrors;
use App\Http\Paging\LibraryCursor;
use App\Http\Requests\Library\LibrarySongsPageRequest;
use Illuminate\Http\JsonResponse;

/**
 * "Load more" on a playlist page (spotify-library-tracks, design D4): the next songs and the cursor after them, in
 * JSON and never cached.
 */
class ShowPlaylistSongsPageController extends Controller
{
    use RespondsWithSpotifyErrors;

    public function __construct(private readonly QueryBus $queries) {}

    public function __invoke(LibrarySongsPageRequest $request, string $id): JsonResponse
    {
        try {
            $page = $this->queries->ask(new GetPlaylistSongs($id, $request->offset()));
        } catch (PlaylistUnavailable) {
            return response()->json(['error' => 'playlist_unavailable'], 404, $this->noStore());
        } catch (SpotifyApiFailed $failure) {
            return $this->spotifyError($failure);
        }

        return response()->json([
            'items' => $page->itemsArray(),
            'next_cursor' => $page->nextOffset === null ? null : LibraryCursor::encode($request->scope(), $page->nextOffset),
        ], 200, $this->noStore());
    }
}
