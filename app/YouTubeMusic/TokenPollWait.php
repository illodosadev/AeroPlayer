<?php

namespace App\YouTubeMusic;

/**
 * A token poll answer that keeps the attempt waiting (youtube-music-connection, design D3).
 */
enum TokenPollWait
{
    /** "authorization_pending": poll again after the interval. */
    case Pending;

    /** "slow_down" or a 429: add 5 seconds to the interval first. */
    case SlowDown;

    /** A server error or no answer: poll again after the same interval. */
    case Retry;
}
