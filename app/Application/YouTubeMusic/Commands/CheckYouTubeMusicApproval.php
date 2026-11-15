<?php

namespace App\Application\YouTubeMusic\Commands;

use App\Bus\Command;

/**
 * Ask Google whether the owner approved the waiting device code, when its interval has passed, and store the
 * connection once the owner's account approves it (youtube-music-connection, design D3).
 */
final readonly class CheckYouTubeMusicApproval implements Command
{
    public function __construct(public int $userId) {}
}
