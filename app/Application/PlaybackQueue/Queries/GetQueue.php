<?php

namespace App\Application\PlaybackQueue\Queries;

use App\Application\PlaybackQueue\Results\QueueState;
use App\Bus\Query;

/**
 * The owner's whole queue (playback-queue, design D4). An owner who never used it gets an empty queue in version 0.
 *
 * @implements Query<QueueState>
 */
final readonly class GetQueue implements Query
{
    public function __construct(public int $userId) {}
}
