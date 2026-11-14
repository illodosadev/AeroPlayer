<?php

namespace App\Application\Spotify\Results;

/**
 * What the Spotify settings page shows (spotify-connection, design D9): display data only, never a token, an id
 * or the list of scopes.
 */
final readonly class SpotifySettings
{
    /**
     * @param  'not_connected'|'connected'|'reconnect_required'  $status
     */
    public function __construct(
        public bool $configured,
        public string $status,
        public ?string $accountName,
        public ?string $reconnectBefore,
        public bool $missingScopes,
    ) {}
}
