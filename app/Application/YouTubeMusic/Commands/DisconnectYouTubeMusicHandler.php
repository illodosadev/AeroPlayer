<?php

namespace App\Application\YouTubeMusic\Commands;

use App\Repositories\YouTubeMusicConnectionRepository;
use App\YouTubeMusic\GoogleDeviceFlow;
use Illuminate\Contracts\Encryption\DecryptException;

final readonly class DisconnectYouTubeMusicHandler
{
    public function __construct(
        private GoogleDeviceFlow $flow,
        private YouTubeMusicConnectionRepository $connections,
    ) {}

    /**
     * Revoking is best effort: the connection is deleted whether or not Google answers (design D6).
     */
    public function handle(DisconnectYouTubeMusic $command): void
    {
        $connection = $this->connections->current();

        if ($connection === null) {
            return;
        }

        try {
            $token = $connection->refresh_token ?? $connection->access_token;
        } catch (DecryptException) {
            $token = null;
        }

        if (is_string($token) && $token !== '') {
            $this->flow->revoke($token);
        }

        $this->connections->deleteForOwner($command->userId);
    }
}
