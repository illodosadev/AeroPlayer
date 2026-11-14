<?php

namespace App\Spotify;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The Spotify authorization code flow (spotify-connection, design D1 and D2).
 *
 * No request here calls ->throw(): Laravel's RequestException embeds part of the response body in its message,
 * so every failure is mapped to a ConnectFailure instead, and nothing is logged.
 */
class SpotifyOAuth
{
    public const AUTHORIZE_URL = 'https://accounts.spotify.com/authorize';

    public const TOKEN_URL = 'https://accounts.spotify.com/api/token';

    public const ME_URL = 'https://api.spotify.com/v1/me';

    /**
     * Spotify is configured when the client id, the client secret and the owner's Spotify id are all set.
     */
    public function isConfigured(): bool
    {
        return filled($this->clientId()) && filled($this->clientSecret()) && filled($this->ownerId());
    }

    public function ownerId(): string
    {
        return (string) config('services.spotify.owner_id');
    }

    public function redirectUri(): string
    {
        return (string) (config('services.spotify.redirect_uri') ?: url('/spotify/callback'));
    }

    /**
     * The Spotify authorization page, with every required scope and a forced consent dialog.
     */
    public function authorizeUrl(string $state): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'scope' => implode(' ', SpotifyScopes::REQUIRED),
            'state' => $state,
            'show_dialog' => 'true',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchange the authorization code for tokens.
     */
    public function exchangeCode(string $code): SpotifyTokens|ConnectFailure
    {
        try {
            $response = Http::asForm()
                ->withBasicAuth($this->clientId(), $this->clientSecret())
                ->timeout($this->timeout())
                ->post(self::TOKEN_URL, [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $this->redirectUri(),
                ]);
        } catch (ConnectionException) {
            return ConnectFailure::Unavailable;
        }

        if ($this->unavailable($response)) {
            return ConnectFailure::Unavailable;
        }

        $accessToken = $response->json('access_token');
        $refreshToken = $response->json('refresh_token');

        if (! $response->successful() || ! is_string($accessToken) || ! is_string($refreshToken)) {
            return ConnectFailure::ExchangeRejected;
        }

        return new SpotifyTokens(
            $accessToken,
            $refreshToken,
            (int) $response->json('expires_in', 3600),
            (string) $response->json('scope', ''),
        );
    }

    /**
     * Read the account behind an access token from "GET /v1/me".
     */
    public function fetchAccount(string $accessToken): SpotifyAccount|ConnectFailure
    {
        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout($this->timeout())
                ->get(self::ME_URL);
        } catch (ConnectionException) {
            return ConnectFailure::Unavailable;
        }

        if ($this->unavailable($response)) {
            return ConnectFailure::Unavailable;
        }

        if ($response->status() === 403) {
            return ConnectFailure::NotAllowed;
        }

        $id = $response->json('id');

        if (! $response->successful() || ! is_string($id) || $id === '') {
            return ConnectFailure::ExchangeRejected;
        }

        $displayName = $response->json('display_name');
        $product = $response->json('product');

        return new SpotifyAccount(
            $id,
            is_string($displayName) ? $displayName : null,
            is_string($product) ? $product : null,
        );
    }

    private function unavailable(Response $response): bool
    {
        return $response->serverError() || $response->status() === 429;
    }

    private function clientId(): string
    {
        return (string) config('services.spotify.client_id');
    }

    private function clientSecret(): string
    {
        return (string) config('services.spotify.client_secret');
    }

    private function timeout(): int
    {
        return (int) config('services.spotify.timeout', 10);
    }
}
