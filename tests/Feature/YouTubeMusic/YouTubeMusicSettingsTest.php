<?php

// youtube-music-connection, tasks 4.1 and 4.2 (design D7, D8 and D10): the settings page states, the shared prop,
// "YouTube Music tokens stay off the pages" and disconnecting. No test reaches Google.

use App\Models\User;
use App\Models\YouTubeMusicConnection;
use App\YouTubeMusic\ConnectionAttempts;
use App\YouTubeMusic\DeviceCode;
use App\YouTubeMusic\GoogleDeviceFlow;
use App\YouTubeMusic\YouTubeMusicConnectFailure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Http::preventStrayRequests();
    configureYouTubeMusic();
    $this->owner = User::factory()->create();
});

function settingsAttempt(): void
{
    app(ConnectionAttempts::class)->start(new DeviceCode('dev-123', 'ABCD-EFGH', 'https://www.google.com/device', 5, 1800));
}

test('Not connected', function () {
    $this->actingAs($this->owner)
        ->get('/settings/youtube-music')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/YouTubeMusic')
            ->where('configured', true)
            ->where('status', 'not_connected')
            ->where('channelName', null)
            ->where('waiting', null)
            ->where('message', null));
});

test('Connected', function () {
    YouTubeMusicConnection::factory()->for($this->owner)->create();

    $this->actingAs($this->owner)
        ->get('/settings/youtube-music')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('status', 'connected')
            ->where('channelName', 'Alice Music'));
});

test('Access expired or revoked', function () {
    YouTubeMusicConnection::factory()->for($this->owner)->reconnectRequired()->create();

    $this->actingAs($this->owner)
        ->get('/settings/youtube-music')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('status', 'reconnect_required'));
});

test('A YouTube Music setting is missing', function (string $key) {
    config(["services.youtube_music.{$key}" => '']);

    $this->actingAs($this->owner)
        ->get('/settings/youtube-music')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('configured', false));
})->with(['client_id', 'client_secret', 'owner_channel_id']);

test('Starting the connection: the waiting page shows the code and the address, never the device code', function () {
    Http::fake([GoogleDeviceFlow::DEVICE_CODE_URL => Http::response(googleFixture('device-code'))]);

    $this->actingAs($this->owner)->post('/youtube-music/connect');

    $response = $this->actingAs($this->owner)->get('/settings/youtube-music');

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('waiting', ['userCode' => 'ABCD-EFGH', 'verificationUrl' => 'https://www.google.com/device']));

    expect($response->getContent())->not->toContain('dev-123')->not->toContain('secret-xyz');
});

test('an expired attempt no longer shows as waiting', function () {
    $this->travelTo('2026-11-15 10:00:00');
    settingsAttempt();
    $this->travelTo('2026-11-15 10:30:00');

    $this->actingAs($this->owner)
        ->get('/settings/youtube-music')
        ->assertInertia(fn (Assert $page) => $page->where('waiting', null));
});

test('each final status flashes its message for the page', function (YouTubeMusicConnectFailure $failure) {
    $this->actingAs($this->owner)
        ->withSession(['youtubeMusicMessage' => ['tone' => $failure->tone(), 'text' => $failure->message()]])
        ->get('/settings/youtube-music')
        ->assertInertia(fn (Assert $page) => $page->where('message.text', $failure->message()));
})->with(YouTubeMusicConnectFailure::cases());

test('the final messages are the texts of the specification', function () {
    expect(array_map(fn (YouTubeMusicConnectFailure $failure) => $failure->message(), YouTubeMusicConnectFailure::cases()))
        ->toBe([
            "Couldn't reach Google. Try again in a moment.",
            'YouTube Music is not configured correctly.',
            'You denied access to YouTube Music.',
            'The code expired. Start again.',
            "This YouTube account isn't yours. Nothing was connected.",
            "Couldn't check your YouTube account. Try again.",
            "Couldn't connect YouTube Music. Try again.",
        ]);
});

test('Shared status for each connection state', function () {
    $shared = fn () => $this->actingAs($this->owner)->get('/dashboard')->inertiaProps('youtubeMusic');

    expect($shared())->toBe(['status' => 'not_connected', 'connected' => false]);

    $connection = YouTubeMusicConnection::factory()->for($this->owner)->create();

    expect($shared())->toBe(['status' => 'connected', 'connected' => true]);

    $connection->update(['status' => 'reconnect_required']);

    expect($shared())->toBe(['status' => 'reconnect_required', 'connected' => false]);
});

test('The settings page holds no tokens', function () {
    YouTubeMusicConnection::factory()->for($this->owner)->create();

    $page = $this->actingAs($this->owner)->get('/settings/youtube-music')->getContent();
    $status = $this->actingAs($this->owner)->getJson('/youtube-music/connect/status')->getContent();

    foreach ([$page, $status] as $body) {
        expect($body)->not->toContain('ya29.access-123')->not->toContain('1//refresh-456');
    }
});

test('A visitor opens the YouTube Music settings', function () {
    $this->get('/settings/youtube-music')->assertRedirect('/login');

    Http::assertNothingSent();
});

// --- Disconnecting (task 4.2, design D6) -----------------------------------------------------------------------

test('Disconnecting YouTube Music', function () {
    Http::fake([GoogleDeviceFlow::REVOKE_URL => Http::response([])]);
    YouTubeMusicConnection::factory()->for($this->owner)->create();

    $this->actingAs($this->owner)
        ->delete('/youtube-music/connection')
        ->assertRedirect('/settings/youtube-music');

    Http::assertSent(fn (Request $request) => $request->url() === GoogleDeviceFlow::REVOKE_URL
        && $request['token'] === '1//refresh-456');

    expect(YouTubeMusicConnection::count())->toBe(0);

    $this->actingAs($this->owner)
        ->get('/settings/youtube-music')
        ->assertInertia(fn (Assert $page) => $page
            ->where('status', 'not_connected')
            ->where('message.text', 'YouTube Music disconnected.')
            ->where('message.detail', fn (string $detail) => str_contains($detail, 'myaccount.google.com/permissions')));
});

test('Disconnecting while Google does not answer', function (mixed $answer) {
    Http::fake([GoogleDeviceFlow::REVOKE_URL => $answer]);
    YouTubeMusicConnection::factory()->for($this->owner)->create();

    $this->actingAs($this->owner)
        ->delete('/youtube-music/connection')
        ->assertRedirect('/settings/youtube-music')
        ->assertSessionHas('youtubeMusicMessage.text', 'YouTube Music disconnected.');

    expect(YouTubeMusicConnection::count())->toBe(0);
})->with([
    'a 503' => fn () => Http::response([], 503),
    'no answer' => fn () => fn () => throw new ConnectionException('timed out'),
]);

test('Disconnecting without a connection', function () {
    $this->actingAs($this->owner)
        ->delete('/youtube-music/connection')
        ->assertRedirect('/settings/youtube-music');

    $this->actingAs($this->owner)->get('/settings/youtube-music')->assertOk();

    Http::assertNothingSent();
});

test('A visitor cannot disconnect', function () {
    YouTubeMusicConnection::factory()->for($this->owner)->create();

    $this->delete('/youtube-music/connection')->assertRedirect('/login');

    expect(YouTubeMusicConnection::count())->toBe(1);
    Http::assertNothingSent();
});
