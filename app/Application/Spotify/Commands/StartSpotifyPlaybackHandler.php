<?php

namespace App\Application\Spotify\Commands;

use App\Application\Spotify\Exceptions\SpotifyApiFailed;
use App\Application\Spotify\Exceptions\SpotifyDeviceNotFound;
use App\Application\Spotify\Exceptions\SpotifyPlaybackRejected;
use App\Application\Spotify\Exceptions\SpotifyPremiumRequired;
use App\Spotify\SpotifyClient;

final readonly class StartSpotifyPlaybackHandler
{
    public function __construct(private SpotifyClient $spotify) {}

    /**
     * Send "PUT /me/player/play" for the device and read Spotify's answer. The client's own failures bubble unchanged.
     *
     * @throws SpotifyApiFailed
     */
    public function handle(StartSpotifyPlayback $command): void
    {
        $body = $command->contextUri !== null
            ? ['context_uri' => $command->contextUri]
            : ['uris' => array_values($command->uris ?? [])];

        $response = $this->spotify->send('PUT', '/me/player/play', [
            'query' => ['device_id' => $command->deviceId],
            'json' => [...$body, 'offset' => $command->offset],
        ]);

        if ($response->successful()) {
            return;
        }

        if ($response->status() === 403 && $response->json('error.reason') === 'PREMIUM_REQUIRED') {
            throw new SpotifyPremiumRequired;
        }

        if ($response->status() === 404) {
            throw new SpotifyDeviceNotFound;
        }

        // A bad URI, or a 403 for another reason: retrying would not help.
        throw new SpotifyPlaybackRejected;
    }
}
