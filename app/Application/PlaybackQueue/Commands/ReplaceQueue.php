<?php

namespace App\Application\PlaybackQueue\Commands;

use App\Application\PlaybackQueue\QueueTrack;
use App\Bus\Command;

/**
 * Replace every song of the owner's queue, with the song at currentIndex as the current one. It carries no version:
 * playing a list is an explicit order and always wins (playback-queue, design D4).
 */
final readonly class ReplaceQueue implements Command
{
    /**
     * @param  list<QueueTrack>  $tracks
     */
    public function __construct(
        public int $userId,
        public array $tracks,
        public int $currentIndex,
    ) {}
}
