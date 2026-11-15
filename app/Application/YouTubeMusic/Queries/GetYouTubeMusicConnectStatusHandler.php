<?php

namespace App\Application\YouTubeMusic\Queries;

use App\Enums\YouTubeMusicConnectionStatus;
use App\Repositories\YouTubeMusicConnectionRepository;
use App\YouTubeMusic\ConnectionAttempts;

final readonly class GetYouTubeMusicConnectStatusHandler
{
    public function __construct(
        private ConnectionAttempts $attempts,
        private YouTubeMusicConnectionRepository $connections,
    ) {}

    /**
     * @return 'pending'|'connected'|'idle'
     */
    public function handle(GetYouTubeMusicConnectStatus $query): string
    {
        if ($this->attempts->current() !== null) {
            return 'pending';
        }

        return $this->connections->status() === YouTubeMusicConnectionStatus::Connected ? 'connected' : 'idle';
    }
}
