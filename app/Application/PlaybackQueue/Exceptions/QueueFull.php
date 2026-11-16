<?php

namespace App\Application\PlaybackQueue\Exceptions;

use App\Application\UseCaseFailed;

/**
 * The write would leave more than 500 songs in the queue (playback-queue, design D4).
 */
final class QueueFull extends UseCaseFailed
{
    public function __construct()
    {
        parent::__construct('The queue is full (500 songs).');
    }
}
