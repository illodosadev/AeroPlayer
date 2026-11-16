<?php

namespace App\Application\PlaybackQueue\Commands;

use App\Bus\Command;

/**
 * Move a pending song to a position among the pending songs, counted from 0 (playback-queue, design D2 and D4).
 */
final readonly class MoveQueueItem implements Command
{
    public function __construct(
        public int $userId,
        public int $version,
        public int $itemId,
        public int $position,
    ) {}
}
