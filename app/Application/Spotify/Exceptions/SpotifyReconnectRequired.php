<?php

namespace App\Application\Spotify\Exceptions;

/**
 * The connection needs reconnecting: it was already marked so, Spotify rejected the refresh, a second 401 came back
 * or the tokens no longer decrypt. The connection is marked reconnect_required and holds no tokens.
 */
final class SpotifyReconnectRequired extends SpotifyApiFailed
{
    public function __construct()
    {
        parent::__construct('Spotify needs to be reconnected.');
    }
}
