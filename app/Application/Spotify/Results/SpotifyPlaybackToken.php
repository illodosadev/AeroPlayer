<?php

namespace App\Application\Spotify\Results;

/**
 * The access token handed to the browser player, with the seconds it stays valid (spotify-playback-api, design D2).
 * Never the refresh token.
 */
final readonly class SpotifyPlaybackToken
{
    public function __construct(
        #[\SensitiveParameter] public string $accessToken,
        public int $expiresIn,
    ) {}
}
