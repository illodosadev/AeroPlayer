<?php

namespace App\Spotify;

/**
 * The tokens Spotify returns for an authorization code. The scope is space-separated, as Spotify sends it.
 */
final readonly class SpotifyTokens
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
        public string $scope,
    ) {}
}
