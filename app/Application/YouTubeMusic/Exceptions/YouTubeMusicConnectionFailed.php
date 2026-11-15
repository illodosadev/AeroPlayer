<?php

namespace App\Application\YouTubeMusic\Exceptions;

use App\Application\UseCaseFailed;
use App\YouTubeMusic\YouTubeMusicConnectFailure;

/**
 * Connecting YouTube Music failed. The reason holds the status, the page message and its tone
 * (youtube-music-connection, design D3).
 */
final class YouTubeMusicConnectionFailed extends UseCaseFailed
{
    public function __construct(public readonly YouTubeMusicConnectFailure $reason)
    {
        parent::__construct('Connecting YouTube Music failed: '.$reason->name.'.');
    }
}
