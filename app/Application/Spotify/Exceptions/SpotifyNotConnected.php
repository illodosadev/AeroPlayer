<?php

namespace App\Application\Spotify\Exceptions;

/**
 * There is no Spotify connection. Nothing was sent to Spotify.
 */
final class SpotifyNotConnected extends SpotifyApiFailed
{
    public function __construct()
    {
        parent::__construct('Spotify is not connected.');
    }
}
