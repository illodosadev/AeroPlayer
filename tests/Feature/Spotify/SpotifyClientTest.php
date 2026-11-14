<?php

// spotify-api-client: the shared Web API client against a faked Spotify (spec "spotify-web-api"). The helper names
// carry a "client" prefix because Pest declares them globally.

use App\Application\Spotify\Exceptions\SpotifyNotConnected;
use App\Application\Spotify\Exceptions\SpotifyRateLimited;
use App\Application\Spotify\Exceptions\SpotifyReconnectRequired;
use App\Application\Spotify\Exceptions\SpotifyUnavailable;
use App\Enums\SpotifyConnectionStatus;
use App\Models\SpotifyConnection;
use App\Repositories\SpotifyConnectionRepository;
use App\Spotify\SpotifyClient;
use App\Spotify\SpotifyOAuth;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia;

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

function clientUnderTest(): SpotifyClient
{
    return app(SpotifyClient::class);
}

function clientApiUrl(string $path): string
{
    return SpotifyClient::API_URL.$path;
}

/**
 * A connection whose access token expires the given number of seconds from now (negative: already expired).
 */
function clientConnectionExpiringIn(int $seconds, array $attributes = []): SpotifyConnection
{
    return SpotifyConnection::factory()->create(['access_token_expires_at' => now()->addSeconds($seconds), ...$attributes]);
}

function clientIsRefresh(Request $request): bool
{
    return $request->url() === SpotifyOAuth::TOKEN_URL;
}

// --- Web API calls need a usable connection ----------------------------------------------------------------------

test('Calling without a connection', function () {
    expect(fn () => clientUnderTest()->get('/me'))->toThrow(SpotifyNotConnected::class);

    Http::assertNothingSent();
});

test('Calling with a connection that needs reconnecting', function () {
    SpotifyConnection::factory()->reconnectRequired()->create();

    expect(fn () => clientUnderTest()->get('/me'))->toThrow(SpotifyReconnectRequired::class);

    Http::assertNothingSent();
});

// --- Access tokens are refreshed before they expire --------------------------------------------------------------

test('An expired token is refreshed', function () {
    $connection = clientConnectionExpiringIn(-10);

    Http::fake([
        SpotifyOAuth::TOKEN_URL => Http::response(['access_token' => 'BQ-new-789', 'expires_in' => 3600]),
        clientApiUrl('/me') => Http::response(['id' => 'owner-123']),
    ]);

    expect(clientUnderTest()->get('/me')->json('id'))->toBe('owner-123');

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => clientIsRefresh($request)
        && $request['grant_type'] === 'refresh_token'
        && $request['refresh_token'] === 'AQ-refresh-456');
    Http::assertSent(fn (Request $request) => $request->url() === clientApiUrl('/me')
        && $request->hasHeader('Authorization', 'Bearer BQ-new-789'));

    $stored = $connection->fresh();

    expect($stored->access_token)->toBe('BQ-new-789')
        ->and($stored->access_token_expires_at->equalTo(now()->addSeconds(3600)))->toBeTrue()
        ->and($stored->refresh_token)->toBe('AQ-refresh-456');
});

test('Spotify rotates the refresh token', function () {
    $connection = clientConnectionExpiringIn(-10);

    Http::fake([
        SpotifyOAuth::TOKEN_URL => Http::response([
            'access_token' => 'BQ-new-789',
            'refresh_token' => 'AQ-rotated-999',
            'expires_in' => 3600,
        ]),
        clientApiUrl('/me') => Http::response(['id' => 'owner-123']),
    ]);

    clientUnderTest()->get('/me');

    expect($connection->fresh()->refresh_token)->toBe('AQ-rotated-999');
});

test('A token expiring within 60 seconds is refreshed first', function () {
    clientConnectionExpiringIn(30);

    Http::fake([
        SpotifyOAuth::TOKEN_URL => Http::response(['access_token' => 'BQ-new-789', 'expires_in' => 3600]),
        clientApiUrl('/me') => Http::response(['id' => 'owner-123']),
    ]);

    clientUnderTest()->get('/me');

    $urls = Http::recorded()->map(fn (array $pair) => $pair[0]->url())->all();

    expect($urls)->toBe([SpotifyOAuth::TOKEN_URL, clientApiUrl('/me')]);
});

test('A valid token is not refreshed', function () {
    clientConnectionExpiringIn(30 * 60);

    Http::fake([clientApiUrl('/me') => Http::response(['id' => 'owner-123'])]);

    clientUnderTest()->get('/me');

    Http::assertNotSent(fn (Request $request) => clientIsRefresh($request));
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer BQ-access-123'));
});

test('a stored token without an expiry is refreshed', function () {
    SpotifyConnection::factory()->create(['access_token_expires_at' => null]);

    Http::fake([
        SpotifyOAuth::TOKEN_URL => Http::response(['access_token' => 'BQ-new-789', 'expires_in' => 3600]),
        clientApiUrl('/me') => Http::response(['id' => 'owner-123']),
    ]);

    clientUnderTest()->get('/me');

    Http::assertSent(fn (Request $request) => clientIsRefresh($request));
});

test('accessToken returns the refreshed token without calling the Web API', function () {
    clientConnectionExpiringIn(-10);

    Http::fake([SpotifyOAuth::TOKEN_URL => Http::response(['access_token' => 'BQ-new-789', 'expires_in' => 3600])]);

    expect(clientUnderTest()->accessToken())->toBe('BQ-new-789');

    Http::assertSentCount(1);
});

test('a query and a JSON body reach the Web API', function () {
    clientConnectionExpiringIn(3600);

    Http::fake(['api.spotify.com/*' => Http::response([], 204)]);

    clientUnderTest()->get('/search', ['q' => 'daft punk', 'type' => 'track']);
    clientUnderTest()->send('PUT', '/me/player/play', ['json' => ['uris' => ['spotify:track:1']]]);

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === clientApiUrl('/search?q=daft%20punk&type=track'));
    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && $request->url() === clientApiUrl('/me/player/play')
        && $request['uris'] === ['spotify:track:1']);
});

// --- Other Web API answers reach the caller ----------------------------------------------------------------------

test('A 403 reaches the feature', function () {
    $connection = clientConnectionExpiringIn(3600);

    Http::fake([clientApiUrl('/me/player') => Http::response(['error' => ['status' => 403]], 403)]);

    expect(clientUnderTest()->get('/me/player')->status())->toBe(403);

    $stored = $connection->fresh();

    expect($stored->status)->toBe(SpotifyConnectionStatus::Connected)
        ->and($stored->access_token)->toBe('BQ-access-123')
        ->and($stored->refresh_token)->toBe('AQ-refresh-456');
});

// --- Parallel calls refresh the token only once (design D4) ------------------------------------------------------

/**
 * Replace the refresh lock with one whose block() first stores "the other call's" fresh token, then runs the client's
 * callback. This is how the re-read inside the lock is exercised in a single process.
 */
function clientLockWhereAnotherCallRefreshes(SpotifyConnection $connection, string $freshToken): void
{
    // A proxy around the real cache manager: lock() is faked, every other call (the pause) reaches the real one.
    // Cache::partialMock() would build a manager without its constructor, and so without the application.
    $cache = Mockery::mock(app('cache'));
    Cache::swap($cache);

    $cache->shouldReceive('lock')
        ->with(SpotifyClient::LOCK_KEY, Mockery::any())
        ->andReturn(new class($connection->connection_id, $freshToken)
        {
            public function __construct(private string $connectionId, private string $freshToken) {}

            public function block(int $seconds, callable $callback): mixed
            {
                app(SpotifyConnectionRepository::class)->storeRefreshedTokens($this->connectionId, $this->freshToken, null, 3600);

                return $callback();
            }
        });
}

test('A refresh already done by another call is reused', function () {
    $connection = clientConnectionExpiringIn(-10);
    clientLockWhereAnotherCallRefreshes($connection, 'BQ-fresh-111');

    Http::fake([clientApiUrl('/me') => Http::response(['id' => 'owner-123'])]);

    clientUnderTest()->get('/me');

    Http::assertNotSent(fn (Request $request) => clientIsRefresh($request));
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer BQ-fresh-111'));
});

test('after a 401, a token another call already refreshed is used without a refresh', function () {
    $connection = clientConnectionExpiringIn(3600);
    clientLockWhereAnotherCallRefreshes($connection, 'BQ-fresh-111');

    Http::fake([clientApiUrl('/me') => Http::sequence()->push([], 401)->push(['id' => 'owner-123'], 200)]);

    expect(clientUnderTest()->get('/me')->status())->toBe(200);

    Http::assertNotSent(fn (Request $request) => clientIsRefresh($request));

    $authorizations = Http::recorded()->map(fn (array $pair) => $pair[0]->header('Authorization')[0])->all();

    expect($authorizations)->toBe(['Bearer BQ-access-123', 'Bearer BQ-fresh-111']);
});

test('a refresh lock that never frees fails as unavailable', function () {
    clientConnectionExpiringIn(-10);
    Http::fake();

    // Another process holds the lock. Fake sleeps move the frozen clock, so the 10 s wait passes instantly.
    Cache::lock(SpotifyClient::LOCK_KEY, 15)->get();
    Sleep::fake(syncWithCarbon: true);

    expect(fn () => clientUnderTest()->get('/me'))->toThrow(SpotifyUnavailable::class);

    Http::assertNothingSent();
});

// --- A rejected token is refreshed and the call retried once -----------------------------------------------------

test('A 401 refreshes the token and retries', function () {
    clientConnectionExpiringIn(3600);

    Http::fake([
        SpotifyOAuth::TOKEN_URL => Http::response(['access_token' => 'BQ-new-789', 'expires_in' => 3600]),
        clientApiUrl('/me') => Http::sequence()->push([], 401)->push(['id' => 'owner-123'], 200),
    ]);

    expect(clientUnderTest()->get('/me')->status())->toBe(200);

    $sent = Http::recorded()->map(fn (array $pair) => [$pair[0]->url(), $pair[0]->header('Authorization')[0] ?? null])->all();

    expect($sent)->toHaveCount(3)
        ->and($sent[0])->toBe([clientApiUrl('/me'), 'Bearer BQ-access-123'])
        ->and($sent[1][0])->toBe(SpotifyOAuth::TOKEN_URL)
        ->and($sent[2])->toBe([clientApiUrl('/me'), 'Bearer BQ-new-789']);
});

test('A 401 after the refresh asks to reconnect', function () {
    $connection = clientConnectionExpiringIn(3600);

    Http::fake([
        SpotifyOAuth::TOKEN_URL => Http::response(['access_token' => 'BQ-new-789', 'expires_in' => 3600]),
        clientApiUrl('/me') => Http::response([], 401),
    ]);

    expect(fn () => clientUnderTest()->get('/me'))->toThrow(SpotifyReconnectRequired::class);

    $urls = Http::recorded()->map(fn (array $pair) => $pair[0]->url());

    expect($urls->filter(fn (string $url) => $url === clientApiUrl('/me')))->toHaveCount(2)
        ->and($urls->filter(fn (string $url) => $url === SpotifyOAuth::TOKEN_URL))->toHaveCount(1);

    $stored = $connection->fresh();

    expect($stored->status)->toBe(SpotifyConnectionStatus::ReconnectRequired)
        ->and($stored->access_token)->toBeNull()
        ->and($stored->refresh_token)->toBeNull();
});

// --- Revoked or unreadable access asks to reconnect --------------------------------------------------------------

test('Access revoked on Spotify or refresh token expired', function () {
    $connection = clientConnectionExpiringIn(-10);

    Http::fake([SpotifyOAuth::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400)]);

    expect(fn () => clientUnderTest()->get('/me'))->toThrow(SpotifyReconnectRequired::class);

    $stored = $connection->fresh();

    expect($stored->status)->toBe(SpotifyConnectionStatus::ReconnectRequired)
        ->and($stored->access_token)->toBeNull()
        ->and($stored->refresh_token)->toBeNull();

    // The page renders "Your Spotify access expired or was revoked…" and "Connect Spotify" from these props
    // (spotify-connection, tests/Browser/SpotifySettingsTest.php covers the rendering).
    $this->actingAs($connection->user)
        ->get('/settings/spotify')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('status', 'reconnect_required')
            ->where('configured', true));
});

test('Tokens encrypted with a previous application key', function () {
    $connection = clientConnectionExpiringIn(3600);
    $previousKey = new Encrypter(random_bytes(32), 'AES-256-CBC');

    DB::table('spotify_connections')->update([
        'access_token' => $previousKey->encrypt('BQ-old-key', false),
        'refresh_token' => $previousKey->encrypt('AQ-old-key', false),
    ]);

    Http::fake();

    expect(fn () => clientUnderTest()->get('/me'))->toThrow(SpotifyReconnectRequired::class);

    Http::assertNothingSent();

    $stored = $connection->fresh();

    expect($stored->status)->toBe(SpotifyConnectionStatus::ReconnectRequired)
        ->and($stored->access_token)->toBeNull()
        ->and($stored->refresh_token)->toBeNull();
});

// --- Web API failures carry no secrets ---------------------------------------------------------------------------

test('A failed refresh exposes no secret', function () {
    clientConnectionExpiringIn(-10);

    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
        $logged[] = $event->message.' '.json_encode($event->context);
    });

    Http::fake([SpotifyOAuth::TOKEN_URL => Http::response(['error' => 'invalid_grant', 'echo' => 'AQ-refresh-456'], 400)]);

    try {
        clientUnderTest()->get('/me');
        $this->fail('The call should fail.');
    } catch (SpotifyReconnectRequired $failure) {
        // Even a failure Laravel reports to the log stays clean.
        report($failure);
    }

    $everything = $failure->getMessage().' '.$failure->getTraceAsString().' '.implode(' ', $logged);

    expect($everything)
        ->not->toContain('AQ-refresh-456')
        ->not->toContain('BQ-access-123')
        ->not->toContain('secret-xyz');
});

// --- Spotify not responding leaves the tokens untouched ----------------------------------------------------------

/**
 * The stored tokens, read through the app, to compare before and after a failed call.
 *
 * @return array{0: ?string, 1: ?string}
 */
function clientStoredTokens(SpotifyConnection $connection): array
{
    $stored = $connection->fresh();

    return [$stored->access_token, $stored->refresh_token];
}

test('Spotify down during the refresh', function () {
    $connection = clientConnectionExpiringIn(-10);
    $before = clientStoredTokens($connection);

    Http::fake([SpotifyOAuth::TOKEN_URL => Http::response([], 503)]);

    expect(fn () => clientUnderTest()->get('/me'))->toThrow(SpotifyUnavailable::class)
        ->and(clientStoredTokens($connection))->toBe($before);
});

test('Spotify does not answer in time', function () {
    $connection = clientConnectionExpiringIn(3600);
    $before = clientStoredTokens($connection);

    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    expect(fn () => clientUnderTest()->get('/me'))->toThrow(SpotifyUnavailable::class)
        ->and(clientStoredTokens($connection))->toBe($before);
});

test('The Web API answers 5xx', function () {
    clientConnectionExpiringIn(3600);

    Http::fake([clientApiUrl('/me') => Http::response([], 502)]);

    expect(fn () => clientUnderTest()->get('/me'))->toThrow(SpotifyUnavailable::class);
});

test('an invalid_client answer to the refresh fails as unavailable and keeps the tokens', function () {
    $connection = clientConnectionExpiringIn(-10);
    $before = clientStoredTokens($connection);

    Http::fake([SpotifyOAuth::TOKEN_URL => Http::response(['error' => 'invalid_client'], 400)]);

    expect(fn () => clientUnderTest()->get('/me'))->toThrow(SpotifyUnavailable::class)
        ->and(clientStoredTokens($connection))->toBe($before)
        ->and($connection->fresh()->status)->toBe(SpotifyConnectionStatus::Connected);
});

// --- A rate limit pauses every Spotify request -------------------------------------------------------------------

/**
 * Expect the call to fail as rate limited with exactly this wait.
 */
function clientExpectRateLimited(Closure $call, int $seconds): void
{
    expect($call)->toThrow(function (SpotifyRateLimited $failure) use ($seconds) {
        expect($failure->retryAfter)->toBe($seconds);
    });
}

test('A 429 with Retry-After', function () {
    clientConnectionExpiringIn(3600);

    Http::fake([clientApiUrl('/me/playlists') => Http::response([], 429, ['Retry-After' => '5'])]);

    clientExpectRateLimited(fn () => clientUnderTest()->get('/me/playlists'), 5);
});

test('The pause is shared and sends nothing', function () {
    clientConnectionExpiringIn(3600);

    Http::fake([
        clientApiUrl('/me/playlists') => Http::response([], 429, ['Retry-After' => '5']),
        clientApiUrl('/search*') => Http::response(['tracks' => []]),
    ]);

    clientExpectRateLimited(fn () => clientUnderTest()->get('/me/playlists'), 5);

    $this->travel(2)->seconds();
    clientExpectRateLimited(fn () => clientUnderTest()->get('/search', ['q' => 'daft punk', 'type' => 'track']), 3);
    Http::assertSentCount(1);

    $this->travel(4)->seconds();
    expect(clientUnderTest()->get('/search', ['q' => 'daft punk', 'type' => 'track'])->ok())->toBeTrue();
    Http::assertSentCount(2);
});

test('A 429 without Retry-After', function () {
    clientConnectionExpiringIn(3600);

    Http::fake([clientApiUrl('/me') => Http::response([], 429)]);

    clientExpectRateLimited(fn () => clientUnderTest()->get('/me'), 5);

    $this->travel(4)->seconds();
    clientExpectRateLimited(fn () => clientUnderTest()->get('/me'), 1);
    Http::assertSentCount(1);
});

test('a non-numeric Retry-After falls back to 5 seconds', function () {
    clientConnectionExpiringIn(3600);

    Http::fake([clientApiUrl('/me') => Http::response([], 429, ['Retry-After' => 'Wed, 21 Oct 2026 07:28:00 GMT'])]);

    clientExpectRateLimited(fn () => clientUnderTest()->get('/me'), 5);
});

test('A 429 from the token endpoint starts the pause too', function () {
    $connection = clientConnectionExpiringIn(-10);
    $before = clientStoredTokens($connection);

    Http::fake([SpotifyOAuth::TOKEN_URL => Http::response([], 429, ['Retry-After' => '5'])]);

    clientExpectRateLimited(fn () => clientUnderTest()->get('/me'), 5);

    Http::assertNotSent(fn (Request $request) => $request->url() === clientApiUrl('/me'));
    expect(clientStoredTokens($connection))->toBe($before);
});

test('No refresh during the pause', function () {
    clientConnectionExpiringIn(-10);

    Http::fake([SpotifyOAuth::TOKEN_URL => Http::response([], 429, ['Retry-After' => '5'])]);

    clientExpectRateLimited(fn () => clientUnderTest()->get('/me'), 5);

    $this->travel(2)->seconds();
    clientExpectRateLimited(fn () => clientUnderTest()->get('/me'), 3);

    // Only the first refresh reached Spotify: no refresh and no Web API call during the pause.
    Http::assertSentCount(1);
});
