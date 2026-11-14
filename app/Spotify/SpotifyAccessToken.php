<?php

namespace App\Spotify;

/**
 * A fresh access token and the whole number of seconds it stays valid (spotify-playback-api, design D2).
 */
final readonly class SpotifyAccessToken
{
    public function __construct(
        #[\SensitiveParameter] public string $value,
        public int $expiresIn,
    ) {}
}
