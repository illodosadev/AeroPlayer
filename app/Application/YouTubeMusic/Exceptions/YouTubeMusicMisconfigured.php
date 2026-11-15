<?php

namespace App\Application\YouTubeMusic\Exceptions;

/**
 * Google refused the client, or the client is not configured: reconnecting would not fix it.
 */
final class YouTubeMusicMisconfigured extends YouTubeMusicCredentialsFailed
{
    public function __construct()
    {
        parent::__construct('YouTube Music is not configured correctly.');
    }
}
