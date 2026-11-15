<?php

namespace App\YouTubeMusic;

/**
 * The tokens Google returns when the owner approves the device code. The scope is space-separated, as Google sends
 * it.
 */
final readonly class GoogleTokens
{
    public function __construct(
        #[\SensitiveParameter] public string $accessToken,
        #[\SensitiveParameter] public ?string $refreshToken,
        public int $expiresIn,
        public string $scope,
    ) {}
}
