<?php

namespace App\Application\PlaybackQueue\Exceptions;

use App\Application\UseCaseFailed;

/**
 * The version the client sent is not the stored one: the queue changed on another device (playback-queue, design D4).
 */
final class QueueVersionConflict extends UseCaseFailed
{
    public function __construct()
    {
        parent::__construct('The queue changed on another device.');
    }
}
