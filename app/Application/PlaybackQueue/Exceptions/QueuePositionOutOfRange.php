<?php

namespace App\Application\PlaybackQueue\Exceptions;

use App\Application\UseCaseFailed;

/**
 * The position is past the pending songs (playback-queue, design D4).
 */
final class QueuePositionOutOfRange extends UseCaseFailed
{
    public function __construct()
    {
        parent::__construct('The position is outside the pending songs.');
    }
}
