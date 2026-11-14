<?php

// spotify-playback-api, task 2.1: "The browser player gets a fresh access token" and "The token endpoint answers
// failures in JSON".

use App\Models\SpotifyConnection;
use App\Models\User;
use App\Spotify\SpotifyClient;
use App\Spotify\SpotifyOAuth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->freezeSecond();

    config()->set('services.spotify', [
        'client_id' => 'client-abc',
        'client_secret' => 'secret-xyz',
        'owner_id' => 'owner-123',
        'redirect_uri' => 'http://127.0.0.1:8000/spotify/callback',
        'timeout' => 10,
    ]);
});

function playbackTokenConnection(int $expiresIn, array $attributes = []): SpotifyConnection
{
    return SpotifyConnection::factory()->create([
        'access_token_expires_at' => now()->addSeconds($expiresIn),
        ...$attributes,
    ]);
}

test('The endpoint hands over the current token', function () {
    $connection = playbackTokenConnection(3000, ['access_token' => 'tok-123', 'refresh_token' => 'AQ-refresh-456']);
    Http::fake();

    $response = $this->actingAs($connection->user)->getJson('/spotify/token');

    $response->assertOk()
        ->assertExactJson(['access_token' => 'tok-123', 'expires_in' => 3000])
        ->assertDontSee('AQ-refresh-456');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    Http::assertNothingSent();
});

test('An expiring token is refreshed before it is handed over', function () {
    $connection = playbackTokenConnection(30);

    Http::fake([SpotifyOAuth::TOKEN_URL => Http::response(['access_token' => 'tok-456', 'expires_in' => 3600])]);

    $this->actingAs($connection->user)
        ->getJson('/spotify/token')
        ->assertOk()
        ->assertExactJson(['access_token' => 'tok-456', 'expires_in' => 3600]);
});

test('a guest JSON request gets the JSON 401', function () {
    $this->getJson('/spotify/token')
        ->assertUnauthorized()
        ->assertExactJson(['error' => 'unauthenticated']);
});

test('a guest plain request is still sent to the login page', function () {
    $this->get('/spotify/token')->assertRedirect('/login');
});

test('the owner without a Spotify connection gets 409 spotify_not_connected', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/spotify/token')
        ->assertStatus(409)
        ->assertExactJson(['error' => 'spotify_not_connected']);
});

test('a refresh rejected with invalid_grant gets 409 spotify_reconnect_required', function () {
    $connection = playbackTokenConnection(-10);

    Http::fake([SpotifyOAuth::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400)]);

    $this->actingAs($connection->user)
        ->getJson('/spotify/token')
        ->assertStatus(409)
        ->assertExactJson(['error' => 'spotify_reconnect_required']);
});

test('a token endpoint answering 503 gets 502 spotify_unavailable', function () {
    $connection = playbackTokenConnection(-10);

    Http::fake([SpotifyOAuth::TOKEN_URL => Http::response([], 503)]);

    $this->actingAs($connection->user)
        ->getJson('/spotify/token')
        ->assertStatus(502)
        ->assertExactJson(['error' => 'spotify_unavailable']);
});

test('The token endpoint during the rate-limit pause', function () {
    $connection = playbackTokenConnection(3600);
    Cache::put(SpotifyClient::PAUSE_KEY, now()->getTimestamp() + 5, 5);
    Http::fake();

    $this->actingAs($connection->user)
        ->getJson('/spotify/token')
        ->assertStatus(429)
        ->assertExactJson(['error' => 'rate_limited', 'retry_after' => 5]);

    Http::assertNothingSent();
});
