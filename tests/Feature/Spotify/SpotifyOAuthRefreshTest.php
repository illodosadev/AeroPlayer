<?php

// spotify-api-client, task 1.3 (design D4 and D7): the refresh request to Spotify's token endpoint.

use App\Spotify\SpotifyOAuth;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    config()->set('services.spotify', [
        'client_id' => 'client-abc',
        'client_secret' => 'secret-xyz',
        'owner_id' => 'owner-123',
        'redirect_uri' => 'http://127.0.0.1:8000/spotify/callback',
        'timeout' => 10,
    ]);
});

test('the refresh request carries the grant, the refresh token and the client credentials', function () {
    Http::fake([SpotifyOAuth::TOKEN_URL => Http::response(['access_token' => 'BQ-new-789', 'expires_in' => 3600])]);

    $response = app(SpotifyOAuth::class)->requestRefresh('AQ-refresh-456');

    expect($response->json('access_token'))->toBe('BQ-new-789');

    Http::assertSent(fn (Request $request) => $request->url() === SpotifyOAuth::TOKEN_URL
        && $request->method() === 'POST'
        && $request['grant_type'] === 'refresh_token'
        && $request['refresh_token'] === 'AQ-refresh-456'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('client-abc:secret-xyz')));
});

test('an error answer is returned, not thrown', function () {
    Http::fake([SpotifyOAuth::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400)]);

    expect(app(SpotifyOAuth::class)->requestRefresh('AQ-refresh-456')->status())->toBe(400);
});

test('the refresh token parameter is sensitive', function () {
    expect((new ReflectionParameter([SpotifyOAuth::class, 'requestRefresh'], 'refreshToken'))
        ->getAttributes(SensitiveParameter::class))->not->toBe([]);
});
