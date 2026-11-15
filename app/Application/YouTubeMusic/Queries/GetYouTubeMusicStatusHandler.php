<?php

namespace App\Application\YouTubeMusic\Queries;

use App\Enums\YouTubeMusicConnectionStatus;
use App\Repositories\YouTubeMusicConnectionRepository;

final readonly class GetYouTubeMusicStatusHandler
{
    public function __construct(private YouTubeMusicConnectionRepository $connections) {}

    public function handle(GetYouTubeMusicStatus $query): ?YouTubeMusicConnectionStatus
    {
        return $this->connections->status();
    }
}
