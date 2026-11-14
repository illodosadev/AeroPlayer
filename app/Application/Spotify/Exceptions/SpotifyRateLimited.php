<?php

namespace App\Application\Spotify\Exceptions;

/**
 * Spotify's rate limit is in force. Every Spotify request is paused for the remaining seconds.
 */
final class SpotifyRateLimited extends SpotifyApiFailed
{
    /**
     * @param  int  $retryAfter  seconds until requests may reach Spotify again, at least 1
     */
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('Spotify rate limit reached.');
    }
}
