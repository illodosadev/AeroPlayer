<?php

namespace App\Application\YouTubeMusic\Commands;

use App\Application\YouTubeMusic\Exceptions\YouTubeMusicConnectionFailed;
use App\Repositories\YouTubeMusicConnectionRepository;
use App\YouTubeMusic\ConnectionAttempt;
use App\YouTubeMusic\ConnectionAttempts;
use App\YouTubeMusic\GoogleDeviceFlow;
use App\YouTubeMusic\GoogleTokens;
use App\YouTubeMusic\TokenPollWait;
use App\YouTubeMusic\YouTubeMusicConnectFailure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

final readonly class CheckYouTubeMusicApprovalHandler
{
    /**
     * The lock outlives the worst case under it: the token call, the channel call and a revocation, 10 s each
     * (design D4).
     */
    private const LOCK_SECONDS = 40;

    private const LOCK_WAIT = 35;

    public function __construct(
        private GoogleDeviceFlow $flow,
        private ConnectionAttempts $attempts,
        private YouTubeMusicConnectionRepository $connections,
    ) {}

    /**
     * @throws YouTubeMusicConnectionFailed when the attempt ends without a connection
     */
    public function handle(CheckYouTubeMusicApproval $command): void
    {
        $attempt = $this->attempts->current();

        if ($attempt === null) {
            return;
        }

        // An expired code never reaches Google, whatever the pacing (design D3).
        if ($attempt->isExpired(now()->getTimestamp())) {
            $this->attempts->end($attempt->id);

            throw new YouTubeMusicConnectionFailed(YouTubeMusicConnectFailure::Expired);
        }

        if (! $attempt->isDue(now()->getTimestamp())) {
            return;
        }

        try {
            $failure = Cache::lock(ConnectionAttempts::LOCK_KEY, self::LOCK_SECONDS)->block(
                self::LOCK_WAIT,
                fn () => $this->pollLocked($attempt->id, $command->userId),
            );
        } catch (LockTimeoutException) {
            // Another request is still polling: this one simply answers "pending".
            return;
        }

        if ($failure !== null) {
            throw new YouTubeMusicConnectionFailed($failure);
        }
    }

    /**
     * Inside the lock the attempt is read again: one replaced, ended or already polled by another request is left
     * alone (design D4).
     */
    private function pollLocked(string $attemptId, int $userId): ?YouTubeMusicConnectFailure
    {
        $attempt = $this->attempts->current();
        $now = now()->getTimestamp();

        if ($attempt === null || $attempt->id !== $attemptId) {
            return null;
        }

        if ($attempt->isExpired($now)) {
            $this->attempts->end($attempt->id);

            return YouTubeMusicConnectFailure::Expired;
        }

        if (! $attempt->isDue($now)) {
            return null;
        }

        $answer = $this->flow->pollToken($attempt->deviceCode);

        if ($answer instanceof TokenPollWait) {
            $this->attempts->save($attempt->rescheduled($now, slowDown: $answer === TokenPollWait::SlowDown));

            return null;
        }

        if ($answer instanceof YouTubeMusicConnectFailure) {
            $this->attempts->end($attempt->id);

            return $answer;
        }

        return $this->connect($attempt, $answer, $userId);
    }

    /**
     * Keep the connection only for the owner's channel; otherwise revoke what Google gave and store nothing
     * (design D6).
     */
    private function connect(ConnectionAttempt $attempt, GoogleTokens $tokens, int $userId): ?YouTubeMusicConnectFailure
    {
        $channel = $this->flow->fetchChannel($tokens->accessToken);
        $this->attempts->end($attempt->id);

        if ($channel instanceof YouTubeMusicConnectFailure) {
            $this->flow->revoke($tokens->refreshToken ?? $tokens->accessToken);

            return YouTubeMusicConnectFailure::ChannelUnverifiable;
        }

        if ($channel === null || $channel->id !== $this->flow->ownerChannelId()) {
            $this->flow->revoke($tokens->refreshToken ?? $tokens->accessToken);

            return YouTubeMusicConnectFailure::NotOwner;
        }

        $this->connections->storeForOwner($userId, $channel, $tokens);

        return null;
    }
}
