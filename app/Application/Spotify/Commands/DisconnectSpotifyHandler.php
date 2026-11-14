<?php

namespace App\Application\Spotify\Commands;

use App\Repositories\SpotifyConnectionRepository;

final readonly class DisconnectSpotifyHandler
{
    public function __construct(private SpotifyConnectionRepository $connections) {}

    public function handle(DisconnectSpotify $command): void
    {
        $this->connections->deleteForOwner($command->userId);
    }
}
