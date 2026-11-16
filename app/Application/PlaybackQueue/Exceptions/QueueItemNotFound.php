<?php

namespace App\Application\PlaybackQueue\Exceptions;

use App\Application\UseCaseFailed;

/**
 * The item is not in the owner's queue (playback-queue, design D4).
 */
final class QueueItemNotFound extends UseCaseFailed
{
    public function __construct()
    {
        parent::__construct('The song is not in the queue.');
    }
}
