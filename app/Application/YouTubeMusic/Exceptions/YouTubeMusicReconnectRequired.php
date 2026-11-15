<?php

namespace App\Application\YouTubeMusic\Exceptions;

/**
 * The YouTube Music access expired, was revoked or can no longer be read: the owner has to reconnect.
 */
final class YouTubeMusicReconnectRequired extends YouTubeMusicCredentialsFailed
{
    public function __construct()
    {
        parent::__construct('YouTube Music needs to be reconnected.');
    }
}
