<?php

namespace App\Application\PlaybackQueue\Commands;

use App\Bus\Command;

/**
 * Make a song of the owner's queue the current one, without reordering anything (playback-queue, design D2 and D4).
 */
final readonly class ChangeQueueCurrent implements Command
{
    public function __construct(
        public int $userId,
        public int $version,
        public int $itemId,
    ) {}
}
