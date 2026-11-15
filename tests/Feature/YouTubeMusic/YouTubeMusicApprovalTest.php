<?php

// youtube-music-connection, tasks 3.2 and 3.3 (design D3, D4 and D6): waiting for the owner's approval. The clock is
// frozen and moved by hand, and every call to Google's token endpoint is counted. No test reaches Google.

use App\Enums\YouTubeMusicConnectionStatus;
use App\Models\User;
use App\Models\YouTubeMusicConnection;
use App\YouTubeMusic\ConnectionAttempts;
use App\YouTubeMusic\DeviceCode;
use App\YouTubeMusic\GoogleDeviceFlow;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Http::preventStrayRequests();
    configureYouTubeMusic();
    $this->owner = User::factory()->create();
    $this->start = Carbon::parse('2026-11-15 10:00:00');
    $this->travelTo($this->start);
});

/**
 * Fake Google. The token endpoint plays $tokenAnswers in order and repeats the last one; each answer is
 * [status, body] or "timeout". Returns the log of token calls: the second they happened at and their device code.
 *
 * @param  list<array{0: int, 1: array<string, mixed>}|string>  $tokenAnswers
 */
function approvalGoogle(array $tokenAnswers, mixed $channels = null): ArrayObject
{
    $calls = new ArrayObject;

    Http::fake([
        GoogleDeviceFlow::TOKEN_URL => function (Request $request) use ($calls, &$tokenAnswers) {
            $calls[] = ['at' => now()->getTimestamp(), 'device_code' => $request['device_code']];
            $answer = count($tokenAnswers) > 1 ? array_shift($tokenAnswers) : $tokenAnswers[0];

            if ($answer === 'timeout') {
                throw new ConnectionException('timed out');
            }

            return Http::response($answer[1], $answer[0]);
        },
        GoogleDeviceFlow::CHANNELS_URL.'*' => $channels ?? Http::response(googleFixture('channels-owner')),
        GoogleDeviceFlow::REVOKE_URL => Http::response([]),
    ]);

    return $calls;
}

function approvalAttempt(string $deviceCode = 'dev-123', int $interval = 5, int $expiresIn = 1800): void
{
    app(ConnectionAttempts::class)->start(new DeviceCode($deviceCode, 'ABCD-EFGH', 'https://www.google.com/device', $interval, $expiresIn));
}

function approvalPending(): array
{
    return [428, ['error' => 'authorization_pending']];
}

function approvalApproved(array $overrides = []): array
{
    return [200, googleFixture('token', $overrides)];
}

/**
 * Request the status as the owner at this many seconds after the start.
 */
function approvalStatusAt(object $test, int $second): TestResponse
{
    $test->travelTo($test->start->copy()->addSeconds($second));

    return $test->actingAs($test->owner)->getJson('/youtube-music/connect/status');
}

function approvalRevocations(): int
{
    return count(Http::recorded(fn (Request $request) => $request->url() === GoogleDeviceFlow::REVOKE_URL));
}

// --- Pacing ----------------------------------------------------------------------------------------------------

test("The server respects Google's interval", function () {
    $calls = approvalGoogle([approvalPending()]);
    approvalAttempt();

    foreach ([0, 2, 5, 7] as $second) {
        approvalStatusAt($this, $second)->assertOk()->assertExactJson(['status' => 'pending']);
    }

    expect(array_column($calls->getArrayCopy(), 'at'))
        ->toBe([$this->start->getTimestamp(), $this->start->getTimestamp() + 5]);
});

test('Google asks to slow down', function () {
    $calls = approvalGoogle([[403, ['error' => 'slow_down']], approvalPending()]);
    approvalAttempt();

    foreach ([0, 5, 9, 10] as $second) {
        approvalStatusAt($this, $second)->assertExactJson(['status' => 'pending']);
    }

    expect(array_column($calls->getArrayCopy(), 'at'))
        ->toBe([$this->start->getTimestamp(), $this->start->getTimestamp() + 10]);
});

test('The token endpoint fails while waiting', function (array|string $failure, int $nextCall) {
    $calls = approvalGoogle([$failure, approvalPending()]);
    approvalAttempt();

    foreach ([0, 5, 9, 10] as $second) {
        approvalStatusAt($this, $second)->assertOk()->assertExactJson(['status' => 'pending']);
    }

    expect(array_column($calls->getArrayCopy(), 'at')[1] ?? null)->toBe($this->start->getTimestamp() + $nextCall);
})->with([
    'a 503' => [[503, ['error' => 'upstream-body-detail']], 5],
    'no answer' => ['timeout', 5],
    'a 429' => [[429, []], 10],
]);

// --- Approval --------------------------------------------------------------------------------------------------

test("Connecting the owner's account", function () {
    $calls = approvalGoogle([approvalApproved()]);
    approvalAttempt();

    approvalStatusAt($this, 0)->assertOk()->assertExactJson(['status' => 'connected']);

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), GoogleDeviceFlow::CHANNELS_URL)
        && $request['part'] === 'snippet'
        && $request['mine'] === 'true');

    $connection = YouTubeMusicConnection::sole();

    expect($connection->channel_id)->toBe('UC-owner')
        ->and($connection->channel_title)->toBe('Alice Music')
        ->and($connection->scopes)->toBe(GoogleDeviceFlow::SCOPE)
        ->and($connection->access_token)->toBe('ya29.access-123')
        ->and($connection->refresh_token)->toBe('1//refresh-456')
        ->and($connection->access_token_expires_at->getTimestamp())->toBe($this->start->getTimestamp() + 3599)
        ->and($connection->status)->toBe(YouTubeMusicConnectionStatus::Connected);

    // A later status request sends nothing to the token endpoint.
    approvalStatusAt($this, 10)->assertExactJson(['status' => 'connected']);

    expect($calls)->toHaveCount(1)
        ->and(approvalRevocations())->toBe(0);
});

test('Another account approves the code', function (mixed $channels) {
    approvalGoogle([approvalApproved()], $channels);
    approvalAttempt();

    approvalStatusAt($this, 0)->assertExactJson(['status' => 'not_owner'])
        ->assertSessionHas('youtubeMusicMessage', [
            'tone' => 'error',
            'text' => "This YouTube account isn't yours. Nothing was connected.",
        ]);

    Http::assertSent(fn (Request $request) => $request->url() === GoogleDeviceFlow::REVOKE_URL
        && $request['token'] === '1//refresh-456');

    expect(YouTubeMusicConnection::count())->toBe(0)
        ->and(app(ConnectionAttempts::class)->current())->toBeNull();
})->with([
    'another channel' => fn () => Http::response(googleFixture('channels-owner', [
        'items' => [['kind' => 'youtube#channel', 'id' => 'UC-other', 'snippet' => ['title' => 'Bob']]],
    ])),
    'no channel' => fn () => Http::response(googleFixture('channels-none')),
]);

test('The channel cannot be checked', function (mixed $channels) {
    approvalGoogle([approvalApproved()], $channels);
    approvalAttempt();

    approvalStatusAt($this, 0)->assertExactJson(['status' => 'failed'])
        ->assertSessionHas('youtubeMusicMessage', [
            'tone' => 'error',
            'text' => "Couldn't check your YouTube account. Try again.",
        ]);

    expect(approvalRevocations())->toBe(1)
        ->and(YouTubeMusicConnection::count())->toBe(0);
})->with([
    'a 503' => fn () => Http::response([], 503),
    'no answer' => fn () => fn () => throw new ConnectionException('timed out'),
]);

// --- Attempts that end without a connection --------------------------------------------------------------------

test('The owner denies access', function () {
    approvalGoogle([[403, ['error' => 'access_denied']]]);
    approvalAttempt();

    approvalStatusAt($this, 0)->assertExactJson(['status' => 'denied'])
        ->assertSessionHas('youtubeMusicMessage', ['tone' => 'info', 'text' => 'You denied access to YouTube Music.']);

    expect(YouTubeMusicConnection::count())->toBe(0);
});

test('The code expires when Google says so', function () {
    $calls = approvalGoogle([[400, ['error' => 'expired_token']]]);
    approvalAttempt();

    approvalStatusAt($this, 0)->assertExactJson(['status' => 'expired'])
        ->assertSessionHas('youtubeMusicMessage', ['tone' => 'info', 'text' => 'The code expired. Start again.']);

    approvalStatusAt($this, 5)->assertExactJson(['status' => 'idle']);

    expect($calls)->toHaveCount(1);
});

test('The code expires when its time passes', function () {
    $calls = approvalGoogle([approvalPending()]);
    approvalAttempt();

    approvalStatusAt($this, 0)->assertExactJson(['status' => 'pending']);
    approvalStatusAt($this, 1800)->assertExactJson(['status' => 'expired'])
        ->assertSessionHas('youtubeMusicMessage', ['tone' => 'info', 'text' => 'The code expired. Start again.']);
    approvalStatusAt($this, 1805)->assertExactJson(['status' => 'idle']);

    // Only the call at 0 seconds: the expired code never reaches the token endpoint.
    expect($calls)->toHaveCount(1);
});

test('Google rejects the client while waiting', function () {
    approvalGoogle([[401, ['error' => 'invalid_client']]]);
    approvalAttempt();

    approvalStatusAt($this, 0)->assertExactJson(['status' => 'failed'])
        ->assertSessionHas('youtubeMusicMessage', ['tone' => 'error', 'text' => 'YouTube Music is not configured correctly.']);
});

test('Google refuses for another reason', function () {
    approvalGoogle([[400, ['error' => 'invalid_request']]]);
    approvalAttempt();

    approvalStatusAt($this, 0)->assertExactJson(['status' => 'failed'])
        ->assertSessionHas('youtubeMusicMessage', ['tone' => 'error', 'text' => "Couldn't connect YouTube Music. Try again."]);

    expect(YouTubeMusicConnection::count())->toBe(0);
});

test('A failed attempt leaves the connection alone', function (array $answer, int $second) {
    $existing = YouTubeMusicConnection::factory()->for($this->owner)->reconnectRequired()->create();
    approvalGoogle([$answer]);
    approvalAttempt();

    approvalStatusAt($this, $second);

    $connection = YouTubeMusicConnection::sole();

    expect($connection->status)->toBe(YouTubeMusicConnectionStatus::ReconnectRequired)
        ->and($connection->connection_id)->toBe($existing->connection_id)
        ->and($connection->updated_at->equalTo($existing->updated_at))->toBeTrue();
})->with([
    'denied' => [[403, ['error' => 'access_denied']], 0],
    'expired' => [[428, ['error' => 'authorization_pending']], 1800],
]);

test('Reconnecting replaces the connection', function () {
    $existing = YouTubeMusicConnection::factory()->for($this->owner)->reconnectRequired()->create();
    approvalGoogle([approvalApproved(['refresh_token' => '1//refresh-777'])]);
    approvalAttempt();

    approvalStatusAt($this, 0)->assertExactJson(['status' => 'connected']);

    $connection = YouTubeMusicConnection::sole();

    expect($connection->status)->toBe(YouTubeMusicConnectionStatus::Connected)
        ->and($connection->refresh_token)->toBe('1//refresh-777')
        ->and($connection->connection_id)->not->toBe($existing->connection_id);
});

// --- Two status requests at once (design D4) -------------------------------------------------------------------

test('Two status requests at once: the second finds the connection the first stored', function () {
    $calls = approvalGoogle([approvalApproved()]);
    approvalAttempt();
    $owner = $this->owner;

    // A proxy around the real cache manager: lock() is faked, every other call (the attempt) reaches the real one.
    // Its block() first plays "the other request", which connected while this one waited, then runs the callback.
    $cache = Mockery::mock(app('cache'));
    Cache::swap($cache);

    $cache->shouldReceive('lock')
        ->with(ConnectionAttempts::LOCK_KEY, Mockery::any())
        ->andReturn(new class($owner->id)
        {
            public function __construct(private int $userId) {}

            public function block(int $seconds, callable $callback): mixed
            {
                YouTubeMusicConnection::factory()->create(['user_id' => $this->userId]);
                $attempts = app(ConnectionAttempts::class);
                $attempts->end($attempts->current()->id);

                return $callback();
            }
        });

    approvalStatusAt($this, 0)->assertOk()->assertExactJson(['status' => 'connected']);

    expect($calls)->toHaveCount(0)
        ->and(YouTubeMusicConnection::count())->toBe(1);
});

test('Two status requests at once: a lock that never frees answers pending', function () {
    $calls = approvalGoogle([approvalApproved()]);
    approvalAttempt();

    // Another request holds the lock. Fake sleeps move the frozen clock, so the 35 s wait passes instantly.
    Cache::lock(ConnectionAttempts::LOCK_KEY, 40)->get();
    Sleep::fake(syncWithCarbon: true);

    $this->actingAs($this->owner)->getJson('/youtube-music/connect/status')
        ->assertOk()
        ->assertExactJson(['status' => 'pending']);

    expect($calls)->toHaveCount(0);
    Http::assertNothingSent();
});

// --- Without an attempt, a replaced attempt, and the session ---------------------------------------------------

test('No attempt in progress', function () {
    $this->actingAs($this->owner)->getJson('/youtube-music/connect/status')->assertExactJson(['status' => 'idle']);

    Http::assertNothingSent();
});

test('Starting again: no poll carries the previous device code', function () {
    $calls = approvalGoogle([approvalPending()]);
    approvalAttempt('dev-123');
    approvalAttempt('dev-456');

    approvalStatusAt($this, 0)->assertExactJson(['status' => 'pending']);
    approvalStatusAt($this, 5);

    expect(array_column($calls->getArrayCopy(), 'device_code'))->toBe(['dev-456', 'dev-456']);
});

test('The app session expires while waiting for approval', function () {
    approvalAttempt();

    $this->getJson('/youtube-music/connect/status')
        ->assertStatus(401)
        ->assertExactJson(['error' => 'unauthenticated']);

    Http::assertNothingSent();
});

test('the status response is never cached', function () {
    $this->actingAs($this->owner)->getJson('/youtube-music/connect/status')
        ->assertHeader('Cache-Control', 'no-store, private');
});
