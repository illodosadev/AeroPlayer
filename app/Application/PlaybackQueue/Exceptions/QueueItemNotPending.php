<?php

namespace App\Application\PlaybackQueue\Exceptions;

use App\Application\UseCaseFailed;

/**
 * The item is the current song or an already played one, so it cannot be moved (playback-queue, design D4).
 */
final class QueueItemNotPending extends UseCaseFailed
{
    public function __construct()
    {
        parent::__construct('Only pending songs can be moved.');
    }
}
