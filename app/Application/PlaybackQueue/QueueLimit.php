<?php

namespace App\Application\PlaybackQueue;

/**
 * The most songs a queue holds, counting played, current and pending ones (playback-queue, design D2; T-13 and T-14
 * agreed on 500).
 */
final class QueueLimit
{
    public const SONGS = 500;
}
