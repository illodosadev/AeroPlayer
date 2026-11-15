<?php

namespace App\Application\YouTubeMusic\Queries;

use App\Bus\Query;

/**
 * The polling status after a check: "pending" while an attempt waits, otherwise "connected" or "idle"
 * (youtube-music-connection, design D3).
 */
final readonly class GetYouTubeMusicConnectStatus implements Query {}
