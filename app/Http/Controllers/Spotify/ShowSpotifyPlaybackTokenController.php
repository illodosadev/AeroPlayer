<?php

namespace App\Http\Controllers\Spotify;

use App\Application\Spotify\Exceptions\SpotifyApiFailed;
use App\Application\Spotify\Queries\GetSpotifyPlaybackToken;
use App\Bus\QueryBus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Spotify\Concerns\RespondsWithSpotifyErrors;
use Illuminate\Http\JsonResponse;

/**
 * The Web Playback SDK's token (spotify-playback-api, design D1 and D2): the access token and its lifetime, never
 * the refresh token, and never cached.
 */
class ShowSpotifyPlaybackTokenController extends Controller
{
    use RespondsWithSpotifyErrors;

    public function __construct(private readonly QueryBus $queries) {}

    public function __invoke(): JsonResponse
    {
        try {
            $token = $this->queries->ask(new GetSpotifyPlaybackToken);
        } catch (SpotifyApiFailed $failure) {
            return $this->spotifyError($failure);
        }

        return response()->json([
            'access_token' => $token->accessToken,
            'expires_in' => $token->expiresIn,
        ], 200, $this->noStore());
    }
}
