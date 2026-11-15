<?php

namespace App\YouTubeMusic;

use App\Application\YouTubeMusic\Exceptions\YouTubeMusicMisconfigured;
use App\Application\YouTubeMusic\Exceptions\YouTubeMusicNotConnected;
use App\Application\YouTubeMusic\Exceptions\YouTubeMusicReconnectRequired;
use App\Application\YouTubeMusic\Exceptions\YouTubeMusicUnavailable;
use App\Enums\YouTubeMusicConnectionStatus;
use App\Models\YouTubeMusicConnection;
use App\Repositories\YouTubeMusicConnectionRepository;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;

/**
 * The shared YouTube credentials service (youtube-music-credentials, spec "youtube-music-credentials"). Handlers
 * inject it; entry points never use it (CQRS).
 *
 * It gives an access token valid for at least 60 seconds, renewing it under a cache lock, and turns each Google
 * problem into one of four typed failures. It logs nothing, never calls ->throw() and never puts a response body
 * into an exception (design D6).
 */
final class YouTubeMusicCredentials
{
    /**
     * Cache lock that serialises renewals across requests and processes (design D2).
     */
    public const LOCK_KEY = 'youtube-music:token-refresh';

    /**
     * A token that expires in less than this many seconds is renewed before use.
     */
    private const REFRESH_MARGIN = 60;

    private const LOCK_SECONDS = 15;

    private const LOCK_WAIT = 10;

    public function __construct(
        private readonly GoogleDeviceFlow $flow,
        private readonly YouTubeMusicConnectionRepository $connections,
    ) {}

    /**
     * An access token valid for at least 60 seconds (design D1).
     *
     * @throws YouTubeMusicNotConnected
     * @throws YouTubeMusicReconnectRequired
     * @throws YouTubeMusicUnavailable
     * @throws YouTubeMusicMisconfigured
     */
    public function accessToken(): string
    {
        $connection = $this->connections->current() ?? throw new YouTubeMusicNotConnected;
        [$accessToken] = $this->tokensOf($connection);

        if ($this->isFresh($connection)) {
            return $accessToken;
        }

        if (! $this->flow->isConfigured()) {
            $this->connections->markConfigError($connection->connection_id);

            throw new YouTubeMusicMisconfigured;
        }

        try {
            return Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS)->block(
                self::LOCK_WAIT,
                fn () => $this->renewLocked(),
            );
        } catch (LockTimeoutException) {
            throw new YouTubeMusicUnavailable;
        }
    }

    /**
     * Inside the lock the connection is read again (design D2): a token another request already renewed is used as
     * it is, and a connection deleted, replaced or marked meanwhile is handled as it now stands.
     */
    private function renewLocked(): string
    {
        $connection = $this->connections->current() ?? throw new YouTubeMusicNotConnected;
        [$accessToken, $refreshToken] = $this->tokensOf($connection);

        if ($this->isFresh($connection)) {
            return $accessToken;
        }

        try {
            $response = $this->flow->requestRefresh($refreshToken);
        } catch (ConnectionException) {
            throw new YouTubeMusicUnavailable;
        }

        $error = $response->json('error');

        if ($error === 'invalid_grant') {
            $this->connections->markReconnectRequired($connection->connection_id);

            throw new YouTubeMusicReconnectRequired;
        }

        if ($error === 'invalid_client') {
            $this->connections->markConfigError($connection->connection_id);

            throw new YouTubeMusicMisconfigured;
        }

        $newAccessToken = $response->json('access_token');

        // A 5xx, a 429 or any other refusal: Google is not answering as expected, and the connection stays as it is.
        if (! $response->successful() || ! is_string($newAccessToken) || $newAccessToken === '') {
            throw new YouTubeMusicUnavailable;
        }

        $newRefreshToken = $response->json('refresh_token');

        $this->connections->storeRefreshedTokens(
            $connection->connection_id,
            $newAccessToken,
            is_string($newRefreshToken) && $newRefreshToken !== '' ? $newRefreshToken : null,
            (int) $response->json('expires_in', 3600),
        );

        return $newAccessToken;
    }

    /**
     * Both tokens of a usable connection. A connection marked for reconnecting, without tokens or with tokens that no
     * longer decrypt (an APP_KEY change) needs reconnecting, and nothing is sent to Google (design D1).
     *
     * @return array{0: string, 1: string}
     */
    private function tokensOf(YouTubeMusicConnection $connection): array
    {
        if ($connection->status === YouTubeMusicConnectionStatus::ReconnectRequired) {
            throw new YouTubeMusicReconnectRequired;
        }

        try {
            $accessToken = $connection->access_token;
            $refreshToken = $connection->refresh_token;
        } catch (DecryptException) {
            $accessToken = $refreshToken = null;
        }

        if (! is_string($accessToken) || $accessToken === '' || ! is_string($refreshToken) || $refreshToken === '') {
            $this->connections->markReconnectRequired($connection->connection_id);

            throw new YouTubeMusicReconnectRequired;
        }

        return [$accessToken, $refreshToken];
    }

    /**
     * A token is fresh when it is valid for 60 seconds or more. A missing expiry counts as expired.
     */
    private function isFresh(YouTubeMusicConnection $connection): bool
    {
        return $connection->access_token_expires_at !== null
            && $connection->access_token_expires_at->greaterThanOrEqualTo(now()->addSeconds(self::REFRESH_MARGIN));
    }
}
