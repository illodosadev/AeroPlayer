<?php

namespace App\Application\Library\Concerns;

use App\Application\Spotify\Exceptions\SpotifyNotConnected;
use App\Application\Spotify\Exceptions\SpotifyReconnectRequired;
use App\Enums\SpotifyConnectionStatus;
use App\Models\SpotifyConnection;
use App\Repositories\SpotifyConnectionRepository;

/**
 * The library handlers' first step (spotify-library-overview and spotify-library-tracks, design D1): a missing or
 * revoked connection fails before anything is read, so it never shows kept or partial data.
 */
trait ReadsUsableConnection
{
    /**
     * @throws SpotifyNotConnected
     * @throws SpotifyReconnectRequired
     */
    private function usableConnection(SpotifyConnectionRepository $connections): SpotifyConnection
    {
        $connection = $connections->current() ?? throw new SpotifyNotConnected;

        if ($connection->status === SpotifyConnectionStatus::ReconnectRequired) {
            throw new SpotifyReconnectRequired;
        }

        return $connection;
    }
}
