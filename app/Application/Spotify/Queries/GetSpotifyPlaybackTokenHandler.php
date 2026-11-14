<?php

namespace App\Application\Spotify\Queries;

use App\Application\Spotify\Exceptions\SpotifyApiFailed;
use App\Application\Spotify\Results\SpotifyPlaybackToken;
use App\Spotify\SpotifyClient;

final readonly class GetSpotifyPlaybackTokenHandler
{
    public function __construct(private SpotifyClient $spotify) {}

    /**
     * @throws SpotifyApiFailed
     */
    public function handle(GetSpotifyPlaybackToken $query): SpotifyPlaybackToken
    {
        $token = $this->spotify->accessToken();

        return new SpotifyPlaybackToken($token->value, $token->expiresIn);
    }
}
