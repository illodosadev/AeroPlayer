<?php

namespace App\Application\Spotify\Exceptions;

/**
 * Spotify does not know the playback device (404), for example after the browser player disconnected.
 */
final class SpotifyDeviceNotFound extends SpotifyApiFailed
{
    public function __construct()
    {
        parent::__construct('The Spotify playback device was not found.');
    }
}
