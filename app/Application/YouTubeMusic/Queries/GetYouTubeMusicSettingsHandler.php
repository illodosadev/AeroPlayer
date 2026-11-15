<?php

namespace App\Application\YouTubeMusic\Queries;

use App\Application\YouTubeMusic\Results\YouTubeMusicSettings;
use App\Repositories\YouTubeMusicConnectionRepository;
use App\YouTubeMusic\ConnectionAttempts;
use App\YouTubeMusic\GoogleDeviceFlow;

final readonly class GetYouTubeMusicSettingsHandler
{
    public function __construct(
        private GoogleDeviceFlow $flow,
        private ConnectionAttempts $attempts,
        private YouTubeMusicConnectionRepository $connections,
    ) {}

    public function handle(GetYouTubeMusicSettings $query): YouTubeMusicSettings
    {
        $connection = $this->connections->current();
        $attempt = $this->attempts->current();

        // An expired attempt is no longer worth showing: the next status request ends it (design D3).
        $waiting = $attempt !== null && ! $attempt->isExpired(now()->getTimestamp())
            ? ['userCode' => $attempt->userCode, 'verificationUrl' => $attempt->verificationUrl]
            : null;

        return new YouTubeMusicSettings(
            configured: $this->flow->isConfigured(),
            status: $connection?->status->value ?? 'not_connected',
            channelName: $connection ? ($connection->channel_title ?: $connection->channel_id) : null,
            waiting: $waiting,
        );
    }
}
