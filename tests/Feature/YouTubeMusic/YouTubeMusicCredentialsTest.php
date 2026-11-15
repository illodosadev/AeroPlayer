<?php

// youtube-music-credentials, tasks 2.2 and 2.3 (design D1 and D2): the shared credentials service against faked
// Google answers, with a frozen clock. No test reaches Google.

use App\Application\YouTubeMusic\Exceptions\YouTubeMusicMisconfigured;
use App\Application\YouTubeMusic\Exceptions\YouTubeMusicNotConnected;
use App\Application\YouTubeMusic\Exceptions\YouTubeMusicReconnectRequired;
use App\Application\YouTubeMusic\Exceptions\YouTubeMusicUnavailable;
use App\Enums\YouTubeMusicConnectionStatus;
use App\Models\YouTubeMusicConnection;
use App\Repositories\YouTubeMusicConnectionRepository;
use App\YouTubeMusic\GoogleDeviceFlow;
use App\YouTubeMusic\YouTubeMusicCredentials;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Http::preventStrayRequests();
    configureYouTubeMusic();
    $this->travelTo('2026-11-15 10:00:00');
});

function credentials(): YouTubeMusicCredentials
{
    return app(YouTubeMusicCredentials::class);
}

/**
 * A connected connection whose access token expires this many seconds from now (negative: already expired).
 */
function credentialsConnectionExpiringIn(int $seconds, array $attributes = []): YouTubeMusicConnection
{
    return YouTubeMusicConnection::factory()->create(['access_token_expires_at' => now()->addSeconds($seconds), ...$attributes]);
}

function credentialsRenewals(): int
{
    return count(Http::recorded(fn (Request $request) => $request->url() === GoogleDeviceFlow::TOKEN_URL));
}

// --- A usable connection ---------------------------------------------------------------------------------------

test('No connection', function () {
    expect(fn () => credentials()->accessToken())->toThrow(YouTubeMusicNotConnected::class);

    Http::assertNothingSent();
});

test('Connection waiting for a reconnect', function () {
    YouTubeMusicConnection::factory()->reconnectRequired()->create();

    expect(fn () => credentials()->accessToken())->toThrow(YouTubeMusicReconnectRequired::class);

    Http::assertNothingSent();
});

test('Missing tokens', function () {
    $connection = YouTubeMusicConnection::factory()->create(['access_token' => null, 'refresh_token' => null]);

    expect(fn () => credentials()->accessToken())->toThrow(YouTubeMusicReconnectRequired::class)
        ->and($connection->fresh()->status)->toBe(YouTubeMusicConnectionStatus::ReconnectRequired);

    Http::assertNothingSent();
});

test('Tokens that no longer decrypt', function () {
    $connection = credentialsConnectionExpiringIn(3600);
    $otherKey = new Encrypter(random_bytes(32), 'AES-256-CBC');
    DB::table('youtube_music_connections')->where('id', $connection->id)->update([
        'access_token' => $otherKey->encryptString('ya29.access-123'),
        'refresh_token' => $otherKey->encryptString('1//refresh-456'),
    ]);

    expect(fn () => credentials()->accessToken())->toThrow(YouTubeMusicReconnectRequired::class)
        ->and($connection->fresh()->status)->toBe(YouTubeMusicConnectionStatus::ReconnectRequired);

    Http::assertNothingSent();
});

// --- Fresh and renewed tokens ----------------------------------------------------------------------------------

test('Token valid for an hour', function () {
    credentialsConnectionExpiringIn(3600);

    expect(credentials()->accessToken())->toBe('ya29.access-123');

    Http::assertNothingSent();
});

test('a token valid for exactly 60 seconds is still fresh, and one valid for 59 is renewed', function () {
    Http::fake([GoogleDeviceFlow::TOKEN_URL => Http::response(['access_token' => 'ya29.new-789', 'expires_in' => 3599])]);
    $connection = credentialsConnectionExpiringIn(60);

    expect(credentials()->accessToken())->toBe('ya29.access-123')
        ->and(credentialsRenewals())->toBe(0);

    $connection->update(['access_token_expires_at' => now()->addSeconds(59)]);

    expect(credentials()->accessToken())->toBe('ya29.new-789')
        ->and(credentialsRenewals())->toBe(1);
});

test('Token expired a minute ago', function () {
    Http::fake([GoogleDeviceFlow::TOKEN_URL => Http::response(['access_token' => 'ya29.new-789', 'expires_in' => 3599])]);
    $connection = credentialsConnectionExpiringIn(-60);

    expect(credentials()->accessToken())->toBe('ya29.new-789');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === GoogleDeviceFlow::TOKEN_URL
        && $request['grant_type'] === 'refresh_token'
        && $request['refresh_token'] === '1//refresh-456'
        && $request['client_id'] === 'client-abc'
        && $request['client_secret'] === 'secret-xyz');

    $stored = $connection->fresh();
    $raw = implode(' ', array_map('strval', (array) DB::table('youtube_music_connections')->first()));

    expect($stored->access_token)->toBe('ya29.new-789')
        ->and($stored->access_token_expires_at->equalTo(now()->addSeconds(3599)))->toBeTrue()
        ->and($stored->refresh_token)->toBe('1//refresh-456')
        ->and($stored->status)->toBe(YouTubeMusicConnectionStatus::Connected)
        ->and($raw)->not->toContain('ya29.new-789');

    $this->actingAs($connection->user)->get('/settings/youtube-music')
        ->assertInertia(fn (Assert $page) => $page->where('status', 'connected')->where('message', null));
});

test('Google rotates the refresh token', function () {
    Http::fake([GoogleDeviceFlow::TOKEN_URL => Http::response([
        'access_token' => 'ya29.new-789',
        'refresh_token' => '1//refresh-777',
        'expires_in' => 3599,
    ])]);
    $connection = credentialsConnectionExpiringIn(-60);

    credentials()->accessToken();

    expect($connection->fresh()->refresh_token)->toBe('1//refresh-777');
});

// --- Refused renewals ------------------------------------------------------------------------------------------

test('The authorization expired or was revoked', function () {
    Http::fake([GoogleDeviceFlow::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400)]);
    $connection = credentialsConnectionExpiringIn(-60);

    expect(fn () => credentials()->accessToken())->toThrow(YouTubeMusicReconnectRequired::class);

    $stored = $connection->fresh();

    expect($stored->status)->toBe(YouTubeMusicConnectionStatus::ReconnectRequired)
        ->and($stored->access_token)->toBeNull()
        ->and($stored->refresh_token)->toBeNull();

    $this->actingAs($connection->user)->get('/settings/youtube-music')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('status', 'reconnect_required'));
});

test('The renewal fails for a moment', function (mixed $answer) {
    Http::fake([GoogleDeviceFlow::TOKEN_URL => $answer]);
    $connection = credentialsConnectionExpiringIn(-60);

    expect(fn () => credentials()->accessToken())->toThrow(YouTubeMusicUnavailable::class);

    $stored = $connection->fresh();

    expect($stored->status)->toBe(YouTubeMusicConnectionStatus::Connected)
        ->and($stored->access_token)->toBe('ya29.access-123')
        ->and($stored->refresh_token)->toBe('1//refresh-456')
        ->and($stored->config_error_at)->toBeNull();
})->with([
    'a 503' => fn () => Http::response(['error' => 'upstream-body-detail'], 503),
    'a 429' => fn () => Http::response([], 429),
    'no answer' => fn () => fn () => throw new ConnectionException('timed out'),
    'another refusal' => fn () => Http::response(['error' => 'unauthorized_client'], 400),
    'a 2xx without a token' => fn () => Http::response([]),
]);

test('The client secret changed', function () {
    Http::fake([GoogleDeviceFlow::TOKEN_URL => Http::response(['error' => 'invalid_client'], 401)]);
    $connection = credentialsConnectionExpiringIn(-60);

    expect(fn () => credentials()->accessToken())->toThrow(YouTubeMusicMisconfigured::class);

    $stored = $connection->fresh();

    expect($stored->status)->toBe(YouTubeMusicConnectionStatus::Connected)
        ->and($stored->access_token)->toBe('ya29.access-123')
        ->and($stored->config_error_at)->not->toBeNull();
});

test('The client is no longer configured', function () {
    config(['services.youtube_music.client_secret' => '']);
    $connection = credentialsConnectionExpiringIn(-60);

    expect(fn () => credentials()->accessToken())->toThrow(YouTubeMusicMisconfigured::class)
        ->and($connection->fresh()->config_error_at)->not->toBeNull();

    Http::assertNothingSent();
});

test('The secret is fixed', function () {
    Http::fake([GoogleDeviceFlow::TOKEN_URL => Http::response(['access_token' => 'ya29.new-789', 'expires_in' => 3599])]);
    $connection = credentialsConnectionExpiringIn(-60, ['config_error_at' => now()->subHour()]);

    credentials()->accessToken();

    expect($connection->fresh()->config_error_at)->toBeNull();
});

// --- One renewal at a time (task 2.3, design D2) ----------------------------------------------------------------

/**
 * Replace the renewal lock with one whose block() first plays "the other request", then runs the service's callback.
 * This is how the re-read inside the lock is exercised in a single process.
 */
function credentialsLockWhereAnotherRequest(Closure $otherRequest): void
{
    // A proxy around the real cache manager: lock() is faked, every other call reaches the real one.
    $cache = Mockery::mock(app('cache'));
    Cache::swap($cache);

    $cache->shouldReceive('lock')
        ->with(YouTubeMusicCredentials::LOCK_KEY, Mockery::any())
        ->andReturn(new class($otherRequest)
        {
            public function __construct(private Closure $otherRequest) {}

            public function block(int $seconds, callable $callback): mixed
            {
                ($this->otherRequest)();

                return $callback();
            }
        });
}

test('Two requests at once: the second uses the token the first renewed', function () {
    Http::fake();
    $connection = credentialsConnectionExpiringIn(-60);

    credentialsLockWhereAnotherRequest(fn () => app(YouTubeMusicConnectionRepository::class)
        ->storeRefreshedTokens($connection->connection_id, 'ya29.fresh-111', null, 3599));

    expect(credentials()->accessToken())->toBe('ya29.fresh-111');

    Http::assertNothingSent();
});

test('the re-read inside the lock finds the connection deleted or marked meanwhile', function (string $change, string $failure) {
    Http::fake();
    $connection = credentialsConnectionExpiringIn(-60);

    credentialsLockWhereAnotherRequest(fn () => $change === 'deleted'
        ? $connection->delete()
        : app(YouTubeMusicConnectionRepository::class)->markReconnectRequired($connection->connection_id));

    expect(fn () => credentials()->accessToken())->toThrow($failure);

    Http::assertNothingSent();
})->with([
    'deleted' => ['deleted', YouTubeMusicNotConnected::class],
    'marked for reconnecting' => ['marked', YouTubeMusicReconnectRequired::class],
]);

test('Another renewal never ends', function () {
    Http::fake();
    credentialsConnectionExpiringIn(-60);

    // Another request holds the lock. Fake sleeps move the frozen clock, so the 10 s wait passes instantly.
    Cache::lock(YouTubeMusicCredentials::LOCK_KEY, 15)->get();
    Sleep::fake(syncWithCarbon: true);

    expect(fn () => credentials()->accessToken())->toThrow(YouTubeMusicUnavailable::class);

    Http::assertNothingSent();
});
