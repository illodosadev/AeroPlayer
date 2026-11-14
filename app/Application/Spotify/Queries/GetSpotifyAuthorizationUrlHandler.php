<?php

namespace App\Application\Spotify\Queries;

use App\Application\Spotify\Exceptions\SpotifyNotConfigured;
use App\Spotify\SpotifyOAuth;

final readonly class GetSpotifyAuthorizationUrlHandler
{
    public function __construct(private SpotifyOAuth $oauth) {}

    /**
     * @throws SpotifyNotConfigured
     */
    public function handle(GetSpotifyAuthorizationUrl $query): string
    {
        if (! $this->oauth->isConfigured()) {
            throw new SpotifyNotConfigured;
        }

        return $this->oauth->authorizeUrl($query->state);
    }
}
