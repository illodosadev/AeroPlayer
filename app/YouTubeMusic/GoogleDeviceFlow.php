<?php

namespace App\YouTubeMusic;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Google's OAuth device flow for the owner's YouTube Music connection (youtube-music-connection, design D1).
 *
 * No request here calls ->throw(): Laravel's RequestException embeds part of the response body in its message, so
 * every answer is mapped to a value or a YouTubeMusicConnectFailure instead, and nothing is logged.
 */
class GoogleDeviceFlow
{
    /**
     * Read-only access, so decision D6 (no write permissions) holds (T-27).
     */
    public const SCOPE = 'https://www.googleapis.com/auth/youtube.readonly';

    public const DEVICE_CODE_URL = 'https://oauth2.googleapis.com/device/code';

    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public const CHANNELS_URL = 'https://www.googleapis.com/youtube/v3/channels';

    public const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    private const DEVICE_GRANT = 'urn:ietf:params:oauth:grant-type:device_code';

    /**
     * YouTube Music is configured when the client id, the client secret and the owner's channel id are all set.
     */
    public function isConfigured(): bool
    {
        return filled($this->clientId()) && filled($this->clientSecret()) && filled($this->ownerChannelId());
    }

    public function ownerChannelId(): string
    {
        return (string) config('services.youtube_music.owner_channel_id');
    }

    /**
     * Ask Google for a device code with the read-only scope.
     */
    public function requestDeviceCode(): DeviceCode|YouTubeMusicConnectFailure
    {
        try {
            $response = Http::asForm()->timeout($this->timeout())->post(self::DEVICE_CODE_URL, [
                'client_id' => $this->clientId(),
                'scope' => self::SCOPE,
            ]);
        } catch (ConnectionException) {
            return YouTubeMusicConnectFailure::Unreachable;
        }

        if ($this->unavailable($response)) {
            return YouTubeMusicConnectFailure::Unreachable;
        }

        if ($response->json('error') === 'invalid_client') {
            return YouTubeMusicConnectFailure::Misconfigured;
        }

        $deviceCode = $response->json('device_code');
        $userCode = $response->json('user_code');
        $verificationUrl = $response->json('verification_url');

        if (! $response->successful() || ! is_string($deviceCode) || ! is_string($userCode) || ! is_string($verificationUrl)) {
            return YouTubeMusicConnectFailure::Rejected;
        }

        return new DeviceCode(
            $deviceCode,
            $userCode,
            $verificationUrl,
            max(1, (int) $response->json('interval', 5)),
            max(1, (int) $response->json('expires_in', 1800)),
        );
    }

    /**
     * Ask Google's token endpoint whether the owner approved the device code.
     */
    public function pollToken(#[\SensitiveParameter] string $deviceCode): GoogleTokens|TokenPollWait|YouTubeMusicConnectFailure
    {
        try {
            $response = Http::asForm()->timeout($this->timeout())->post(self::TOKEN_URL, [
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'device_code' => $deviceCode,
                'grant_type' => self::DEVICE_GRANT,
            ]);
        } catch (ConnectionException) {
            return TokenPollWait::Retry;
        }

        if ($response->status() === 429) {
            return TokenPollWait::SlowDown;
        }

        if ($response->serverError()) {
            return TokenPollWait::Retry;
        }

        $accessToken = $response->json('access_token');

        if ($response->successful() && is_string($accessToken) && $accessToken !== '') {
            $refreshToken = $response->json('refresh_token');

            return new GoogleTokens(
                $accessToken,
                is_string($refreshToken) && $refreshToken !== '' ? $refreshToken : null,
                (int) $response->json('expires_in', 3600),
                (string) $response->json('scope', ''),
            );
        }

        // Google signals each case through the "error" field; the status codes vary (428, 403, 400, 401).
        return match ($response->json('error')) {
            'authorization_pending' => TokenPollWait::Pending,
            'slow_down' => TokenPollWait::SlowDown,
            'access_denied' => YouTubeMusicConnectFailure::Denied,
            'expired_token' => YouTubeMusicConnectFailure::Expired,
            'invalid_client' => YouTubeMusicConnectFailure::Misconfigured,
            default => YouTubeMusicConnectFailure::Rejected,
        };
    }

    /**
     * The channel behind an access token. Null means the account has no channel.
     */
    public function fetchChannel(#[\SensitiveParameter] string $accessToken): YouTubeChannel|YouTubeMusicConnectFailure|null
    {
        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout($this->timeout())
                ->get(self::CHANNELS_URL, ['part' => 'snippet', 'mine' => 'true']);
        } catch (ConnectionException) {
            return YouTubeMusicConnectFailure::ChannelUnverifiable;
        }

        if (! $response->successful()) {
            return YouTubeMusicConnectFailure::ChannelUnverifiable;
        }

        $id = $response->json('items.0.id');

        if (! is_string($id) || $id === '') {
            return null;
        }

        $title = $response->json('items.0.snippet.title');

        return new YouTubeChannel($id, is_string($title) ? $title : null);
    }

    /**
     * Try to revoke a token at Google. Any answer, or none, is ignored: revoking is best effort (design D6).
     */
    public function revoke(#[\SensitiveParameter] string $token): void
    {
        try {
            Http::asForm()->timeout($this->timeout())->post(self::REVOKE_URL, ['token' => $token]);
        } catch (ConnectionException) {
            // Best effort: the caller goes on either way.
        }
    }

    private function unavailable(Response $response): bool
    {
        return $response->serverError() || $response->status() === 429;
    }

    private function clientId(): string
    {
        return (string) config('services.youtube_music.client_id');
    }

    private function clientSecret(): string
    {
        return (string) config('services.youtube_music.client_secret');
    }

    private function timeout(): int
    {
        return (int) config('services.youtube_music.timeout', 10);
    }
}
