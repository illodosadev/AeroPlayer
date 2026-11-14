<?php

namespace App\Application\Spotify\Queries;

use App\Bus\Query;

/**
 * Spotify's authorization page for a new single-use state. The caller creates the state and keeps it in the
 * session (spotify-connection, design D5).
 *
 * @implements Query<string>
 */
final readonly class GetSpotifyAuthorizationUrl implements Query
{
    public function __construct(public string $state) {}
}
