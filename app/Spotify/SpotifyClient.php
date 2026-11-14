<?php

namespace App\Spotify;

use App\Application\Spotify\Exceptions\SpotifyNotConnected;
use App\Application\Spotify\Exceptions\SpotifyRateLimited;
use App\Application\Spotify\Exceptions\SpotifyReconnectRequired;
use App\Application\Spotify\Exceptions\SpotifyUnavailable;
use App\Enums\SpotifyConnectionStatus;
use App\Models\SpotifyConnection;
use App\Repositories\SpotifyConnectionRepository;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The shared Spotify Web API client (spotify-api-client, spec "spotify-web-api"). Handlers inject it; entry points
 * never use it (CQRS).
 *
 * It keeps the access token fresh under a cache lock, retries a 401 once, pauses every request after a 429 and turns
 * each Spotify problem into one of four typed failures. It logs nothing, never calls ->throw() and never puts a
 * response body into an exception (design D7).
 */
final class SpotifyClient
{
    public const API_URL = 'https://api.spotify.com/v1';

    /**
     * Cache key of the shared rate-limit pause: the Unix time until which nothing may reach Spotify (design D5).
     */
    public const PAUSE_KEY = 'spotify:paused-until';

    /**
     * Cache lock that serialises token refreshes across requests and processes (design D4).
     */
    public const LOCK_KEY = 'spotify:token-refresh';

    /**
     * A token that expires in less than this many seconds is refreshed before use.
     */
    private const REFRESH_MARGIN = 60;

    /**
     * The pause after a 429 without a usable Retry-After header, in seconds.
     */
    private const DEFAULT_PAUSE = 5;

    private const LOCK_SECONDS = 15;

    private const LOCK_WAIT = 10;

    public function __construct(
        private readonly SpotifyOAuth $oauth,
        private readonly SpotifyConnectionRepository $connections,
    ) {}

    /**
     * GET a Web API path, relative to https://api.spotify.com/v1.
     *
     * @param  array<string, mixed>  $query
     */
    public function get(string $path, array $query = []): Response
    {
        return $this->send('GET', $path, $query === [] ? [] : ['query' => $query]);
    }

    /**
     * Send a Web API request. Any answer other than a 401, a 429 or a 5xx is returned unchanged.
     *
     * @param  array<string, mixed>  $options  Laravel HTTP options, such as "query" or "json"
     *
     * @throws SpotifyNotConnected
     * @throws SpotifyReconnectRequired
     * @throws SpotifyUnavailable
     * @throws SpotifyRateLimited
     */
    public function send(string $method, string $path, array $options = []): Response
    {
        [$connectionId, $accessToken] = $this->freshToken();

        $response = $this->call($method, $path, $options, $accessToken);

        if ($response->status() === 401) {
            $accessToken = $this->refresh($connectionId, rejected: $accessToken);
            $response = $this->call($method, $path, $options, $accessToken);

            if ($response->status() === 401) {
                $this->connections->markReconnectRequired($connectionId);

                throw new SpotifyReconnectRequired;
            }
        }

        return $this->answer($response);
    }

    /**
     * A fresh access token, refreshed under the same lock as the Web API calls. For the Web Playback SDK token
     * endpoint (T-06), so it reuses the refresh instead of copying it (design D1).
     *
     * @throws SpotifyNotConnected
     * @throws SpotifyReconnectRequired
     * @throws SpotifyUnavailable
     * @throws SpotifyRateLimited
     */
    public function accessToken(): string
    {
        return $this->freshToken()[1];
    }

    /**
     * Steps 1 to 3 of design D3: the pause, the connection and a token valid for at least 60 seconds.
     *
     * @return array{0: string, 1: string} the connection identifier and the access token
     */
    private function freshToken(): array
    {
        $this->ensureNotPaused();

        $connection = $this->connections->current() ?? throw new SpotifyNotConnected;
        [$accessToken] = $this->tokensOf($connection);

        if (! $this->isFresh($connection)) {
            $accessToken = $this->refresh($connection->connection_id);
        }

        return [$connection->connection_id, $accessToken];
    }

    /**
     * Refresh the access token under the lock (design D4). Inside the lock the connection is read again: before a
     * call, a token another call already refreshed is used as it is; after a 401, a token that is no longer the
     * rejected one is used as it is.
     */
    private function refresh(string $connectionId, #[\SensitiveParameter] ?string $rejected = null): string
    {
        try {
            return Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS)->block(
                self::LOCK_WAIT,
                fn () => $this->refreshLocked($connectionId, $rejected),
            );
        } catch (LockTimeoutException) {
            throw new SpotifyUnavailable;
        }
    }

    private function refreshLocked(string $connectionId, #[\SensitiveParameter] ?string $rejected): string
    {
        $connection = $this->connections->current() ?? throw new SpotifyNotConnected;
        [$accessToken, $refreshToken] = $this->tokensOf($connection);

        $refreshedByAnotherCall = $rejected === null
            ? $this->isFresh($connection)
            : ! hash_equals($rejected, $accessToken);

        if ($refreshedByAnotherCall) {
            return $accessToken;
        }

        $this->ensureNotPaused();

        try {
            $response = $this->oauth->requestRefresh($refreshToken);
        } catch (ConnectionException) {
            throw new SpotifyUnavailable;
        }

        if ($response->status() === 429) {
            throw $this->pause($response);
        }

        if ($response->status() === 400 && $response->json('error') === 'invalid_grant') {
            $this->connections->markReconnectRequired($connection->connection_id);

            throw new SpotifyReconnectRequired;
        }

        $newAccessToken = $response->json('access_token');

        // A 5xx, or any other refusal such as invalid_client after a secret change: reconnecting would not fix it.
        if (! $response->successful() || ! is_string($newAccessToken) || $newAccessToken === '') {
            throw new SpotifyUnavailable;
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
     * longer decrypt (an APP_KEY change) needs reconnecting, and nothing is sent to Spotify (design D6).
     *
     * @return array{0: string, 1: string}
     */
    private function tokensOf(SpotifyConnection $connection): array
    {
        if ($connection->status === SpotifyConnectionStatus::ReconnectRequired) {
            throw new SpotifyReconnectRequired;
        }

        try {
            $accessToken = $connection->access_token;
            $refreshToken = $connection->refresh_token;
        } catch (DecryptException) {
            $accessToken = $refreshToken = null;
        }

        if (! is_string($accessToken) || $accessToken === '' || ! is_string($refreshToken) || $refreshToken === '') {
            $this->connections->markReconnectRequired($connection->connection_id);

            throw new SpotifyReconnectRequired;
        }

        return [$accessToken, $refreshToken];
    }

    /**
     * A token is fresh when it is valid for 60 seconds or more. A missing expiry counts as expired.
     */
    private function isFresh(SpotifyConnection $connection): bool
    {
        return $connection->access_token_expires_at !== null
            && $connection->access_token_expires_at->greaterThanOrEqualTo(now()->addSeconds(self::REFRESH_MARGIN));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function call(string $method, string $path, array $options, #[\SensitiveParameter] string $accessToken): Response
    {
        try {
            return Http::withToken($accessToken)
                ->acceptJson()
                ->timeout((int) config('services.spotify.timeout', 10))
                ->send($method, self::API_URL.'/'.ltrim($path, '/'), $options);
        } catch (ConnectionException) {
            throw new SpotifyUnavailable;
        }
    }

    /**
     * Step 5 of design D3, after the 401 handling: a 429 starts the pause, a 5xx means unavailable, anything else
     * goes back to the caller.
     */
    private function answer(Response $response): Response
    {
        if ($response->status() === 429) {
            throw $this->pause($response);
        }

        if ($response->serverError()) {
            throw new SpotifyUnavailable;
        }

        return $response;
    }

    /**
     * Fail while the shared pause lasts, with the remaining seconds (design D5).
     */
    private function ensureNotPaused(): void
    {
        $until = Cache::get(self::PAUSE_KEY);
        $now = now()->getTimestamp();

        if (is_int($until) && $until > $now) {
            throw new SpotifyRateLimited($until - $now);
        }
    }

    /**
     * Start the shared pause for the Retry-After seconds, or 5 seconds without a usable header.
     */
    private function pause(Response $response): SpotifyRateLimited
    {
        $header = trim($response->header('Retry-After'));
        $seconds = ctype_digit($header) && (int) $header > 0 ? (int) $header : self::DEFAULT_PAUSE;

        Cache::put(self::PAUSE_KEY, now()->getTimestamp() + $seconds, $seconds);

        return new SpotifyRateLimited($seconds);
    }
}
