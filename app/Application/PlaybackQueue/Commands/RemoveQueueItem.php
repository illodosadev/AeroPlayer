<?php

namespace App\Application\PlaybackQueue\Commands;

use App\Bus\Command;

/**
 * Remove a song from the owner's queue (playback-queue, design D2 and D4).
 */
final readonly class RemoveQueueItem implements Command
{
    public function __construct(
        public int $userId,
        public int $version,
        public int $itemId,
    ) {}
}
