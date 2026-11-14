<?php

namespace App\Application\Spotify\Exceptions;

use App\Application\UseCaseFailed;
use App\Spotify\ConnectFailure;

/**
 * Connecting Spotify failed. The reason holds the page message and its tone (spotify-connection, design D2).
 */
final class SpotifyConnectionFailed extends UseCaseFailed
{
    public function __construct(public readonly ConnectFailure $reason)
    {
        parent::__construct('Connecting Spotify failed: '.$reason->name.'.');
    }
}
