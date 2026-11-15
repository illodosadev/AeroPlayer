<?php

namespace App\YouTubeMusic;

/**
 * The connection attempt waiting for the owner's approval (youtube-music-connection, design D2). Times are Unix
 * timestamps.
 */
final readonly class ConnectionAttempt
{
    public function __construct(
        public string $id,
        #[\SensitiveParameter] public string $deviceCode,
        public string $userCode,
        public string $verificationUrl,
        public int $interval,
        public int $expiresAt,
        public int $nextPollAt,
    ) {}

    public function isExpired(int $now): bool
    {
        return $now >= $this->expiresAt;
    }

    public function isDue(int $now): bool
    {
        return $now >= $this->nextPollAt;
    }

    /**
     * The same attempt, polled again after its interval. "slow_down" adds 5 seconds to the interval first.
     */
    public function rescheduled(int $now, bool $slowDown): self
    {
        $interval = $slowDown ? $this->interval + 5 : $this->interval;

        return new self(
            $this->id,
            $this->deviceCode,
            $this->userCode,
            $this->verificationUrl,
            $interval,
            $this->expiresAt,
            $now + $interval,
        );
    }
}
