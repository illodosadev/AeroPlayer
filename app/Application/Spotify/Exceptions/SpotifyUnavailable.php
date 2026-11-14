<?php

namespace App\Application\Spotify\Exceptions;

/**
 * Spotify answered 5xx, could not be reached or did not answer in time. The stored tokens did not change.
 */
final class SpotifyUnavailable extends SpotifyApiFailed
{
    public function __construct()
    {
        parent::__construct('Spotify is not responding.');
    }
}
