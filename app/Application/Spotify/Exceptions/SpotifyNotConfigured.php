<?php

namespace App\Application\Spotify\Exceptions;

use App\Application\UseCaseFailed;

/**
 * The Spotify client id, client secret or owner id is missing from the configuration.
 */
final class SpotifyNotConfigured extends UseCaseFailed
{
    public function __construct()
    {
        parent::__construct('Spotify is not configured.');
    }
}
