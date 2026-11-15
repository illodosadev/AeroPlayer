<?php

namespace App\Application\YouTubeMusic\Commands;

use App\Application\YouTubeMusic\Exceptions\YouTubeMusicConnectionFailed;
use App\Application\YouTubeMusic\Exceptions\YouTubeMusicNotConfigured;
use App\Enums\YouTubeMusicConnectionStatus;
use App\Repositories\YouTubeMusicConnectionRepository;
use App\YouTubeMusic\ConnectionAttempts;
use App\YouTubeMusic\GoogleDeviceFlow;
use App\YouTubeMusic\YouTubeMusicConnectFailure;

final readonly class StartYouTubeMusicConnectionHandler
{
    public function __construct(
        private GoogleDeviceFlow $flow,
        private ConnectionAttempts $attempts,
        private YouTubeMusicConnectionRepository $connections,
    ) {}

    /**
     * @throws YouTubeMusicNotConfigured
     * @throws YouTubeMusicConnectionFailed
     */
    public function handle(StartYouTubeMusicConnection $command): void
    {
        if (! $this->flow->isConfigured()) {
            throw new YouTubeMusicNotConfigured;
        }

        if ($this->connections->status() === YouTubeMusicConnectionStatus::Connected) {
            return;
        }

        $code = $this->flow->requestDeviceCode();

        if ($code instanceof YouTubeMusicConnectFailure) {
            throw new YouTubeMusicConnectionFailed($code);
        }

        $this->attempts->start($code);
    }
}
