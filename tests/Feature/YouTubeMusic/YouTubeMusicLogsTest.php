<?php

// youtube-music-connection, task 4.3: "YouTube Music secrets are not logged", over the three failures of the
// scenario. Every log entry is captured, with its context, and searched for each secret.

use App\Models\User;
use App\Models\YouTubeMusicConnection;
use App\YouTubeMusic\ConnectionAttempts;
use App\YouTubeMusic\DeviceCode;
use App\YouTubeMusic\GoogleDeviceFlow;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    configureYouTubeMusic();
    $this->owner = User::factory()->create();

    $this->logged = new ArrayObject;
    Event::listen(MessageLogged::class, fn (MessageLogged $event) => $this->logged[] = $event->message.' '.json_encode($event->context));
});

afterEach(function () {
    $all = implode("\n", $this->logged->getArrayCopy());

    foreach (['secret-xyz', 'dev-123', 'ya29.access-123', '1//refresh-456', 'upstream-body-detail'] as $secret) {
        expect($all)->not->toContain($secret);
    }
});

test('a device code request answering 503 logs no secret', function () {
    Http::fake([GoogleDeviceFlow::DEVICE_CODE_URL => Http::response(['error' => 'upstream-body-detail'], 503)]);

    $this->actingAs($this->owner)->post('/youtube-music/connect')->assertRedirect('/settings/youtube-music');
});

test('the token endpoint answering 503 while waiting logs no secret', function () {
    Http::fake([GoogleDeviceFlow::TOKEN_URL => Http::response(['error' => 'upstream-body-detail'], 503)]);
    app(ConnectionAttempts::class)->start(new DeviceCode('dev-123', 'ABCD-EFGH', 'https://www.google.com/device', 5, 1800));

    $this->actingAs($this->owner)->getJson('/youtube-music/connect/status')->assertExactJson(['status' => 'pending']);
});

test('a revocation that does not answer logs no secret', function () {
    Http::fake([GoogleDeviceFlow::REVOKE_URL => fn () => throw new ConnectionException('upstream-body-detail')]);
    YouTubeMusicConnection::factory()->for($this->owner)->create();

    $this->actingAs($this->owner)->delete('/youtube-music/connection')->assertRedirect('/settings/youtube-music');
});
