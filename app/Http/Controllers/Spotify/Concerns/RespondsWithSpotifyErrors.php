<?php

namespace App\Http\Controllers\Spotify\Concerns;

use App\Application\Spotify\Exceptions\SpotifyApiFailed;
use App\Application\Spotify\Exceptions\SpotifyDeviceNotFound;
use App\Application\Spotify\Exceptions\SpotifyNotConnected;
use App\Application\Spotify\Exceptions\SpotifyPlaybackRejected;
use App\Application\Spotify\Exceptions\SpotifyPremiumRequired;
use App\Application\Spotify\Exceptions\SpotifyRateLimited;
use App\Application\Spotify\Exceptions\SpotifyReconnectRequired;
use Illuminate\Http\JsonResponse;

/**
 * The JSON answer of the browser player's endpoints for every Spotify failure (spotify-playback-api, design D5).
 * T-23's endpoints reuse it.
 */
trait RespondsWithSpotifyErrors
{
    protected function spotifyError(SpotifyApiFailed $failure): JsonResponse
    {
        [$status, $body] = match (true) {
            $failure instanceof SpotifyNotConnected => [409, ['error' => 'spotify_not_connected']],
            $failure instanceof SpotifyReconnectRequired => [409, ['error' => 'spotify_reconnect_required']],
            $failure instanceof SpotifyRateLimited => [429, ['error' => 'rate_limited', 'retry_after' => $failure->retryAfter]],
            $failure instanceof SpotifyPremiumRequired => [403, ['error' => 'premium_required']],
            $failure instanceof SpotifyDeviceNotFound => [404, ['error' => 'device_not_found']],
            $failure instanceof SpotifyPlaybackRejected => [422, ['error' => 'playback_rejected']],
            default => [502, ['error' => 'spotify_unavailable']],
        };

        return response()->json($body, $status, $this->noStore());
    }

    /**
     * Every answer of these endpoints, success or error, is kept out of caches.
     *
     * @return array<string, string>
     */
    protected function noStore(): array
    {
        return ['Cache-Control' => 'no-store, private'];
    }
}
