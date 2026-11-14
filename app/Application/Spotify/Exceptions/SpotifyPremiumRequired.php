<?php

namespace App\Application\Spotify\Exceptions;

/**
 * Spotify refused to play because the account has no Premium (403 with reason PREMIUM_REQUIRED).
 */
final class SpotifyPremiumRequired extends SpotifyApiFailed
{
    public function __construct()
    {
        parent::__construct('Spotify Premium is required to play.');
    }
}
