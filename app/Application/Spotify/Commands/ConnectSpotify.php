<?php

namespace App\Application\Spotify\Commands;

use App\Bus\Command;

/**
 * Finish connecting the owner's Spotify account with the authorization code Spotify sent back. The caller has
 * already checked the single-use state (spotify-connection, design D5).
 */
final readonly class ConnectSpotify implements Command
{
    public function __construct(
        public int $userId,
        #[\SensitiveParameter] public string $code,
    ) {}
}
