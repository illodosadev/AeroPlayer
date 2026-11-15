<?php

namespace App\Application\YouTubeMusic\Exceptions;

/**
 * Google did not answer, answered with an error, or another renewal held the lock too long.
 */
final class YouTubeMusicUnavailable extends YouTubeMusicCredentialsFailed
{
    public function __construct()
    {
        parent::__construct('YouTube Music is not responding right now.');
    }
}
