<?php

namespace App\Application\PlaybackQueue\Commands;

use App\Application\PlaybackQueue\QueueTrack;
use App\Bus\Command;

/**
 * Add songs at the end of the owner's queue, from the queue version the client holds (playback-queue, design D4).
 */
final readonly class AddQueueItems implements Command
{
    /**
     * @param  list<QueueTrack>  $tracks
     */
    public function __construct(
        public int $userId,
        public int $version,
        public array $tracks,
    ) {}
}
