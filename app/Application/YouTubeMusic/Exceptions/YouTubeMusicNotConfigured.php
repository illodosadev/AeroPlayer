<?php

namespace App\Application\YouTubeMusic\Exceptions;

use App\Application\UseCaseFailed;

/**
 * The Google client id, client secret or owner channel id is missing from the configuration.
 */
final class YouTubeMusicNotConfigured extends UseCaseFailed
{
    public function __construct()
    {
        parent::__construct('YouTube Music is not configured.');
    }
}
