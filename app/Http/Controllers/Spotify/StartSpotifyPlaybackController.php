<?php

namespace App\Http\Controllers\Spotify;

use App\Application\Spotify\Commands\StartSpotifyPlayback;
use App\Application\Spotify\Exceptions\SpotifyApiFailed;
use App\Bus\CommandBus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Spotify\Concerns\RespondsWithSpotifyErrors;
use App\Http\Requests\Spotify\StartSpotifyPlaybackRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Start an album, playlist or tracks on the browser's device (spotify-playback-api, design D1 and D3). Always JSON:
 * 204 on success, an error code otherwise.
 */
class StartSpotifyPlaybackController extends Controller
{
    use RespondsWithSpotifyErrors;

    public function __construct(private readonly CommandBus $commands) {}

    public function __invoke(StartSpotifyPlaybackRequest $request): Response|JsonResponse
    {
        try {
            $this->commands->dispatch(new StartSpotifyPlayback(
                (string) $request->validated('device_id'),
                $request->validated('context_uri'),
                $request->validated('uris'),
                $request->offset(),
            ));
        } catch (SpotifyApiFailed $failure) {
            return $this->spotifyError($failure);
        }

        return response()->noContent(204, $this->noStore());
    }
}
