<?php

namespace App\Application\YouTubeMusic\Exceptions;

/**
 * There is no YouTube Music connection.
 */
final class YouTubeMusicNotConnected extends YouTubeMusicCredentialsFailed
{
    public function __construct()
    {
        parent::__construct('YouTube Music is not connected.');
    }
}
