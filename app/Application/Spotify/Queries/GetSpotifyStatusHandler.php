<?php

namespace App\Application\Spotify\Queries;

use App\Enums\SpotifyConnectionStatus;
use App\Repositories\SpotifyConnectionRepository;

final readonly class GetSpotifyStatusHandler
{
    public function __construct(private SpotifyConnectionRepository $connections) {}

    public function handle(GetSpotifyStatus $query): ?SpotifyConnectionStatus
    {
        return $this->connections->status();
    }
}
