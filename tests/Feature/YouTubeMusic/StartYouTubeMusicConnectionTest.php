<?php

// youtube-music-connection, task 3.1 (design D2 and D3): starting the device flow. The page itself is checked in
// YouTubeMusicSettingsTest. No test reaches Google.

use App\Models\User;
use App\Models\YouTubeMusicConnection;
use App\YouTubeMusic\ConnectionAttempts;
use App\YouTubeMusic\GoogleDeviceFlow;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    configureYouTubeMusic();
    $this->owner = User::factory()->create();
});

test('Starting the connection', function () {
    Http::fake([GoogleDeviceFlow::DEVICE_CODE_URL => Http::response(googleFixture('device-code'))]);

    $this->actingAs($this->owner)->post('/youtube-music/connect')->assertRedirect('/settings/youtube-music');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === GoogleDeviceFlow::DEVICE_CODE_URL
        && $request['client_id'] === 'client-abc'
        && $request['scope'] === 'https://www.googleapis.com/auth/youtube.readonly');

    $attempt = app(ConnectionAttempts::class)->current();

    expect($attempt->userCode)->toBe('ABCD-EFGH')
        ->and($attempt->verificationUrl)->toBe('https://www.google.com/device')
        ->and($attempt->deviceCode)->toBe('dev-123');
});

test('the device code is stored encrypted in the cache', function () {
    // Production uses the database cache (design D2); the tests default to the array store.
    config(['cache.default' => 'database']);
    Http::fake([GoogleDeviceFlow::DEVICE_CODE_URL => Http::response(googleFixture('device-code'))]);

    $this->actingAs($this->owner)->post('/youtube-music/connect');

    $raw = DB::table('cache')->pluck('value')->implode(' ');

    expect($raw)->not->toBe('')->not->toContain('dev-123');
});

test('Connect requested while connected', function () {
    YouTubeMusicConnection::factory()->for($this->owner)->create();

    $this->actingAs($this->owner)->post('/youtube-music/connect')->assertRedirect('/settings/youtube-music');

    Http::assertNothingSent();
    expect(app(ConnectionAttempts::class)->current())->toBeNull();
});

test('A YouTube Music setting is missing', function (string $key) {
    config(["services.youtube_music.{$key}" => '']);

    $this->actingAs($this->owner)->post('/youtube-music/connect')
        ->assertRedirect('/settings/youtube-music')
        ->assertSessionMissing('youtubeMusicMessage');

    Http::assertNothingSent();
})->with(['client_id', 'client_secret', 'owner_channel_id']);

test('Google does not answer the device code request', function (mixed $answer) {
    Http::fake([GoogleDeviceFlow::DEVICE_CODE_URL => $answer]);

    $this->actingAs($this->owner)->post('/youtube-music/connect')
        ->assertRedirect('/settings/youtube-music')
        ->assertSessionHas('youtubeMusicMessage', ['tone' => 'error', 'text' => "Couldn't reach Google. Try again in a moment."]);

    expect(app(ConnectionAttempts::class)->current())->toBeNull();
})->with([
    '503' => fn () => Http::response(['error' => 'upstream-body-detail'], 503),
    '429' => fn () => Http::response([], 429),
    'no answer' => fn () => fn () => throw new ConnectionException('timed out'),
]);

test('Google rejects the client', function () {
    Http::fake([GoogleDeviceFlow::DEVICE_CODE_URL => Http::response(
        ['error' => 'invalid_client', 'error_description' => 'upstream-body-detail'],
        401,
    )]);

    $response = $this->actingAs($this->owner)->post('/youtube-music/connect');

    $response->assertRedirect('/settings/youtube-music')
        ->assertSessionHas('youtubeMusicMessage', ['tone' => 'error', 'text' => 'YouTube Music is not configured correctly.']);

    expect(json_encode(session()->all()))->not->toContain('upstream-body-detail');
});

test('Starting again replaces the previous code', function () {
    Http::fake([GoogleDeviceFlow::DEVICE_CODE_URL => Http::sequence()
        ->push(googleFixture('device-code'))
        ->push(googleFixture('device-code', ['device_code' => 'dev-456', 'user_code' => 'WXYZ-1234']))]);

    $this->actingAs($this->owner)->post('/youtube-music/connect');
    $this->actingAs($this->owner)->post('/youtube-music/connect');

    $attempt = app(ConnectionAttempts::class)->current();

    expect($attempt->deviceCode)->toBe('dev-456')
        ->and($attempt->userCode)->toBe('WXYZ-1234');
});

test('A visitor cannot start the connection', function () {
    $this->post('/youtube-music/connect')->assertRedirect('/login');

    Http::assertNothingSent();
});
