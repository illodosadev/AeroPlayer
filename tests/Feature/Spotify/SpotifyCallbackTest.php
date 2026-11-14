<?php

// spotify-connection, task 2.3: the callback requirements, from the single-use state to "Spotify secrets are
// not logged". The settings page that shows the flashed message is covered in SpotifySettingsTest.

use App\Enums\SpotifyConnectionStatus;
use App\Models\SpotifyConnection;
use App\Models\User;
use App\Spotify\SpotifyScopes;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

const SPOTIFY_TOKEN_URL = 'accounts.spotify.com/api/token';
const SPOTIFY_ME_URL = 'api.spotify.com/v1/me';

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

function spotifyTokenResponse(string $refresh = 'AQ-refresh-456'): array
{
    return [
        'access_token' => 'BQ-access-123',
        'token_type' => 'Bearer',
        'scope' => implode(' ', SpotifyScopes::REQUIRED),
        'expires_in' => 3600,
        'refresh_token' => $refresh,
    ];
}

/**
 * Fakes the token exchange and "GET /v1/me" with the given answers.
 */
function fakeSpotify(mixed $token = null, mixed $me = null): void
{
    Http::fake([
        SPOTIFY_TOKEN_URL => $token ?? Http::response(spotifyTokenResponse()),
        SPOTIFY_ME_URL => $me ?? Http::response(['id' => 'owner-123', 'display_name' => 'Alice']),
    ]);
}

/**
 * Signs the owner in with the state "state-abc" in the session and sends the callback with the given query.
 */
function spotifyCallback(string $query, ?User $owner = null)
{
    return test()->actingAs($owner ?? User::factory()->create())
        ->withSession(['spotify_oauth_state' => 'state-abc'])
        ->get('/spotify/callback?'.$query);
}

function expectSpotifyMessage($response, string $tone, string $text): void
{
    $response->assertRedirect('/settings/spotify')
        ->assertSessionHas('spotifyMessage', ['tone' => $tone, 'text' => $text]);
}

// --- Success ------------------------------------------------------------------------

test("Connecting the owner's account", function () {
    $this->freezeSecond();
    fakeSpotify();

    $response = spotifyCallback('code=code-123&state=state-abc');

    expectSpotifyMessage($response, 'success', 'Spotify connected.');

    $connection = SpotifyConnection::sole();

    expect($connection->spotify_user_id)->toBe('owner-123')
        ->and($connection->display_name)->toBe('Alice')
        ->and($connection->scopes)->toBe(implode(' ', SpotifyScopes::REQUIRED))
        ->and($connection->access_token_expires_at->equalTo(now()->addSeconds(3600)))->toBeTrue()
        ->and(session('spotify_oauth_state'))->toBeNull();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://accounts.spotify.com/api/token'
        && $request['grant_type'] === 'authorization_code'
        && $request['code'] === 'code-123'
        && $request['redirect_uri'] === 'http://127.0.0.1:8000/spotify/callback'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('client-abc:secret-xyz')));

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.spotify.com/v1/me'
        && $request->hasHeader('Authorization', 'Bearer BQ-access-123'));
});

test('an account that reports Premium connects', function () {
    fakeSpotify(me: Http::response(['id' => 'owner-123', 'display_name' => 'Alice', 'product' => 'premium']));

    expectSpotifyMessage(spotifyCallback('code=code-123&state=state-abc'), 'success', 'Spotify connected.');
    expect(SpotifyConnection::count())->toBe(1);
});

// --- State ---------------------------------------------------------------------------

test('Tampered state', function () {
    fakeSpotify();

    expectSpotifyMessage(spotifyCallback('code=code-123&state=state-other'), 'error', 'Could not connect Spotify. Please try again.');

    Http::assertNothingSent();
    expect(SpotifyConnection::count())->toBe(0);
});

test('Missing state', function () {
    fakeSpotify();

    expectSpotifyMessage(spotifyCallback('code=code-123'), 'error', 'Could not connect Spotify. Please try again.');

    Http::assertNothingSent();
});

test('a callback with no state in the session is rejected', function () {
    fakeSpotify();

    $response = $this->actingAs(User::factory()->create())->get('/spotify/callback?code=code-123&state=state-abc');

    expectSpotifyMessage($response, 'error', 'Could not connect Spotify. Please try again.');
    Http::assertNothingSent();
});

test('Repeated callback', function () {
    fakeSpotify();
    $owner = User::factory()->create();

    spotifyCallback('code=code-123&state=state-abc', $owner);
    $connectionId = SpotifyConnection::sole()->connection_id;

    $response = $this->actingAs($owner)->get('/spotify/callback?code=code-123&state=state-abc');

    expectSpotifyMessage($response, 'error', 'Could not connect Spotify. Please try again.');
    Http::assertSentCount(2);
    expect(SpotifyConnection::sole()->connection_id)->toBe($connectionId);
});

// --- Cancellation --------------------------------------------------------------------

test("The owner cancels on Spotify's page", function () {
    fakeSpotify();

    expectSpotifyMessage(spotifyCallback('error=access_denied&state=state-abc'), 'info', 'Spotify connection was cancelled.');

    Http::assertNothingSent();
    expect(SpotifyConnection::count())->toBe(0);
});

test('Cancellation with a wrong state', function () {
    fakeSpotify();

    expectSpotifyMessage(spotifyCallback('error=access_denied&state=state-other'), 'error', 'Could not connect Spotify. Please try again.');

    Http::assertNothingSent();
});

// --- Account checks ------------------------------------------------------------------

test('Another Spotify account is rejected', function () {
    fakeSpotify(me: Http::response(['id' => 'other-999', 'display_name' => 'Mallory']));

    expectSpotifyMessage(spotifyCallback('code=code-123&state=state-abc'), 'error', "Only the owner's Spotify account can be connected.");

    expect(SpotifyConnection::count())->toBe(0);
});

test("Another account leaves the owner's connection untouched", function () {
    $existing = SpotifyConnection::factory()->create();
    fakeSpotify(token: Http::response(spotifyTokenResponse('AQ-other-000')), me: Http::response(['id' => 'other-999']));

    expectSpotifyMessage(
        spotifyCallback('code=code-123&state=state-abc', $existing->user),
        'error',
        "Only the owner's Spotify account can be connected.",
    );

    $connection = SpotifyConnection::sole();

    expect($connection->spotify_user_id)->toBe('owner-123')
        ->and($connection->refresh_token)->toBe('AQ-refresh-456')
        ->and($connection->connection_id)->toBe($existing->connection_id);
});

test("The owner's account without Premium", function () {
    fakeSpotify(me: Http::response(['id' => 'owner-123', 'display_name' => 'Alice', 'product' => 'free']));

    expectSpotifyMessage(spotifyCallback('code=code-123&state=state-abc'), 'error', 'Spotify Premium is required.');

    expect(SpotifyConnection::count())->toBe(0);
});

test("Account missing from the Spotify app's user list", function () {
    fakeSpotify(me: Http::response(['error' => ['status' => 403, 'message' => 'Forbidden']], 403));

    expectSpotifyMessage(spotifyCallback('code=code-123&state=state-abc'), 'error', 'This Spotify account is not allowed to use this app.');

    expect(SpotifyConnection::count())->toBe(0);
});

// --- Spotify failures ----------------------------------------------------------------

test('Expired or used code', function () {
    fakeSpotify(token: Http::response(['error' => 'invalid_grant', 'error_description' => 'Invalid authorization code'], 400));

    expectSpotifyMessage(spotifyCallback('code=code-old&state=state-abc'), 'error', 'Could not connect Spotify. Please try again.');

    expect(SpotifyConnection::count())->toBe(0);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), SPOTIFY_ME_URL));
});

test('The token endpoint answers 503', function () {
    fakeSpotify(token: Http::response('Service Unavailable', 503));

    expectSpotifyMessage(spotifyCallback('code=code-123&state=state-abc'), 'error', 'Spotify is not responding right now. Try again in a moment.');

    expect(SpotifyConnection::count())->toBe(0);
});

test('a rate-limited exchange counts as Spotify not responding', function () {
    fakeSpotify(token: Http::response('', 429, ['Retry-After' => '30']));

    expectSpotifyMessage(spotifyCallback('code=code-123&state=state-abc'), 'error', 'Spotify is not responding right now. Try again in a moment.');
});

test('The account lookup cannot be reached', function () {
    fakeSpotify(me: fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    expectSpotifyMessage(spotifyCallback('code=code-123&state=state-abc'), 'error', 'Spotify is not responding right now. Try again in a moment.');

    expect(SpotifyConnection::count())->toBe(0);
});

test('the token endpoint timing out counts as Spotify not responding', function () {
    fakeSpotify(token: fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    expectSpotifyMessage(spotifyCallback('code=code-123&state=state-abc'), 'error', 'Spotify is not responding right now. Try again in a moment.');
});

test('a callback without a code is rejected without calling Spotify', function () {
    fakeSpotify();

    expectSpotifyMessage(spotifyCallback('state=state-abc'), 'error', 'Could not connect Spotify. Please try again.');

    Http::assertNothingSent();
});

// --- Reconnecting --------------------------------------------------------------------

test('Reconnecting the same account', function () {
    $existing = SpotifyConnection::factory()->create();
    fakeSpotify(token: Http::response(spotifyTokenResponse('AQ-new-777')));

    spotifyCallback('code=code-123&state=state-abc', $existing->user);

    $connection = SpotifyConnection::sole();

    expect($connection->spotify_user_id)->toBe('owner-123')
        ->and($connection->refresh_token)->toBe('AQ-new-777')
        ->and($connection->connection_id)->not->toBe($existing->connection_id);
});

test('Reconnecting clears the reconnect-required status', function () {
    $existing = SpotifyConnection::factory()->reconnectRequired()->create();
    fakeSpotify();

    spotifyCallback('code=code-123&state=state-abc', $existing->user);

    $connection = SpotifyConnection::sole();

    expect($connection->status)->toBe(SpotifyConnectionStatus::Connected)
        ->and($connection->access_token)->toBe('BQ-access-123')
        ->and($connection->refresh_token)->toBe('AQ-refresh-456');
});

// --- Session and logs ----------------------------------------------------------------

test('Callback without a session', function () {
    fakeSpotify();

    $this->get('/spotify/callback?code=code-123&state=state-abc')->assertRedirect('/login');

    Http::assertNothingSent();
});

test('A failed code exchange logs no secret', function () {
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
        $logged[] = $event->message.' '.json_encode($event->context);
    });

    fakeSpotify(token: Http::response('Service Unavailable', 503));

    expectSpotifyMessage(spotifyCallback('code=code-123&state=state-abc'), 'error', 'Spotify is not responding right now. Try again in a moment.');

    foreach ($logged as $entry) {
        expect($entry)->not->toContain('code-123')->not->toContain('secret-xyz');
    }
});
