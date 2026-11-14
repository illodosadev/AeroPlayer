<?php

namespace App\Application\Spotify\Exceptions;

/**
 * Spotify refused the playback request for another reason, such as an unknown URI. Retrying would not help.
 */
final class SpotifyPlaybackRejected extends SpotifyApiFailed
{
    public function __construct()
    {
        parent::__construct('Spotify rejected the playback request.');
    }
}
