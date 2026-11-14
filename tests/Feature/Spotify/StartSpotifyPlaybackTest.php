<?php

// spotify-playback-api, task 3.2: "Playback starts on the browser's device", "A malformed start request is rejected"
// and "Playback start failures answer in JSON".

use App\Models\SpotifyConnection;
use App\Models\User;
use App\Spotify\SpotifyClient;
use App\Spotify\SpotifyOAuth;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

const START_PLAY_URL = 'https://api.spotify.com/v1/me/player/play?device_id=web-1';

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

/**
 * The owner with a connection whose access token expires the given seconds from now.
 */
function startPlaybackOwner(int $expiresIn = 3600): User
{
    return SpotifyConnection::factory()->create(['access_token_expires_at' => now()->addSeconds($expiresIn)])->user;
}

// --- Playback starts on the browser's device ---------------------------------------------------------------------

test('Starting playback on the web device', function (array $input, array $body) {
    Http::fake(['api.spotify.com/*' => Http::response(null, 204)]);

    $response = $this->actingAs(startPlaybackOwner())
        ->putJson('/spotify/player/play', ['device_id' => 'web-1', ...$input]);

    $response->assertNoContent();
    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && $request->url() === START_PLAY_URL
        && $request->data() == $body
        && $request->hasHeader('Authorization', 'Bearer BQ-access-123'));
})->with([
    'a context from position 0' => [
        ['context_uri' => 'spotify:album:abc', 'offset' => ['position' => 0]],
        ['context_uri' => 'spotify:album:abc', 'offset' => ['position' => 0]],
    ],
    'a context from a track' => [
        ['context_uri' => 'spotify:album:abc', 'offset' => ['uri' => 'spotify:track:t2']],
        ['context_uri' => 'spotify:album:abc', 'offset' => ['uri' => 'spotify:track:t2']],
    ],
    'one track without offset' => [
        ['uris' => ['spotify:track:t1']],
        ['uris' => ['spotify:track:t1'], 'offset' => ['position' => 0]],
    ],
    'two tracks from position 1' => [
        ['uris' => ['spotify:track:t1', 'spotify:track:t2'], 'offset' => ['position' => 1]],
        ['uris' => ['spotify:track:t1', 'spotify:track:t2'], 'offset' => ['position' => 1]],
    ],
]);

// --- A malformed start request is rejected -----------------------------------------------------------------------

test('A malformed start request', function (array $input) {
    Http::fake();

    $this->actingAs(startPlaybackOwner())
        ->putJson('/spotify/player/play', $input)
        ->assertUnprocessable();

    Http::assertNothingSent();
})->with([
    'neither context nor tracks' => [['device_id' => 'web-1']],
    'both context and tracks' => [['device_id' => 'web-1', 'context_uri' => 'spotify:album:abc', 'uris' => ['spotify:track:t1']]],
    'no device' => [['context_uri' => 'spotify:album:abc']],
    'a negative position' => [['device_id' => 'web-1', 'context_uri' => 'spotify:album:abc', 'offset' => ['position' => -1]]],
]);

// --- Playback start failures answer in JSON ----------------------------------------------------------------------

/**
 * Send the scenario's start request as the given user, or as a guest.
 */
function startPlaybackRequest(?User $user): TestResponse
{
    $test = test();

    return ($user ? $test->actingAs($user) : $test)
        ->putJson('/spotify/player/play', ['device_id' => 'web-1', 'context_uri' => 'spotify:album:abc']);
}

test('Playback start failures', function (Closure $situation, int $status, array $json) {
    $user = $situation();

    startPlaybackRequest($user)
        ->assertStatus($status)
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson($json);
})->with([
    'a guest' => [fn () => null, 401, ['error' => 'unauthenticated']],
    'Spotify not connected' => [fn () => User::factory()->create(), 409, ['error' => 'spotify_not_connected']],
    'refresh rejected with invalid_grant' => [function () {
        Http::fake([SpotifyOAuth::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400)]);

        return startPlaybackOwner(-10);
    }, 409, ['error' => 'spotify_reconnect_required']],
    'Premium required' => [function () {
        Http::fake(['api.spotify.com/*' => Http::response(
            ['error' => ['status' => 403, 'message' => 'Player command failed: Premium required', 'reason' => 'PREMIUM_REQUIRED']],
            403,
        )]);

        return startPlaybackOwner();
    }, 403, ['error' => 'premium_required']],
    'device not found' => [function () {
        Http::fake(['api.spotify.com/*' => Http::response(['error' => ['status' => 404, 'message' => 'Device not found']], 404)]);

        return startPlaybackOwner();
    }, 404, ['error' => 'device_not_found']],
    'a 429 from Spotify' => [function () {
        Http::fake(['api.spotify.com/*' => Http::response([], 429, ['Retry-After' => '7'])]);

        return startPlaybackOwner();
    }, 429, ['error' => 'rate_limited', 'retry_after' => 7]],
    'Spotify answering 503' => [function () {
        Http::fake(['api.spotify.com/*' => Http::response([], 503)]);

        return startPlaybackOwner();
    }, 502, ['error' => 'spotify_unavailable']],
    'the Web API timing out' => [function () {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        return startPlaybackOwner();
    }, 502, ['error' => 'spotify_unavailable']],
    'an unknown context' => [function () {
        Http::fake(['api.spotify.com/*' => Http::response(['error' => ['status' => 400, 'message' => 'Invalid context uri']], 400)]);

        return startPlaybackOwner();
    }, 422, ['error' => 'playback_rejected']],
]);

test('the shared rate-limit pause answers 429 without calling Spotify', function () {
    $owner = startPlaybackOwner();
    Cache::put(SpotifyClient::PAUSE_KEY, now()->getTimestamp() + 5, 5);
    Http::fake();

    startPlaybackRequest($owner)
        ->assertStatus(429)
        ->assertExactJson(['error' => 'rate_limited', 'retry_after' => 5]);

    Http::assertNothingSent();
});
