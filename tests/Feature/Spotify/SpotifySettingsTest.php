<?php

// spotify-connection, task 3.1: the settings page states, disconnecting, the shared props and
// "Spotify tokens stay off the pages".

use App\Models\SpotifyConnection;
use App\Models\User;
use App\Spotify\SpotifyScopes;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

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

test('Not connected', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings/spotify')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Spotify')
            ->where('configured', true)
            ->where('status', 'not_connected')
            ->where('accountName', null)
            ->where('reconnectBefore', null)
            ->where('missingScopes', false)
            ->where('message', null));
});

test('Connected with the reconnect date', function () {
    $this->travelTo('2026-11-11 10:00:00');
    $connection = SpotifyConnection::factory()->create(['authorized_at' => now()]);

    $this->actingAs($connection->user)
        ->get('/settings/spotify')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('status', 'connected')
            ->where('accountName', 'Alice')
            ->where('reconnectBefore', '2027-05-11')
            ->where('missingScopes', false));
});

test('Reconnect date at the end of a month', function () {
    $connection = SpotifyConnection::factory()->create(['authorized_at' => '2026-08-31 10:00:00']);

    $this->actingAs($connection->user)
        ->get('/settings/spotify')
        ->assertInertia(fn (Assert $page) => $page->where('reconnectBefore', '2027-02-28'));
});

test('the account id stands in for a missing display name', function () {
    $connection = SpotifyConnection::factory()->create(['display_name' => null]);

    $this->actingAs($connection->user)
        ->get('/settings/spotify')
        ->assertInertia(fn (Assert $page) => $page->where('accountName', 'owner-123'));
});

test('Access expired or revoked', function () {
    $connection = SpotifyConnection::factory()->reconnectRequired()->create();

    $this->actingAs($connection->user)
        ->get('/settings/spotify')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('status', 'reconnect_required')
            ->where('reconnectBefore', null));
});

test('Scopes missing after the app asks for more permissions', function () {
    $granted = implode(' ', array_diff(SpotifyScopes::REQUIRED, ['user-read-playback-state']));
    $connection = SpotifyConnection::factory()->create(['scopes' => $granted]);

    $this->actingAs($connection->user)
        ->get('/settings/spotify')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('status', 'connected')
            ->where('missingScopes', true));
});

test('the not configured page state', function (string $setting) {
    config()->set("services.spotify.{$setting}", '');

    $this->actingAs(User::factory()->create())
        ->get('/settings/spotify')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('configured', false));
})->with(['client_id', 'client_secret', 'owner_id']);

test('a flashed message reaches the page', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['spotifyMessage' => ['tone' => 'error', 'text' => 'Spotify Premium is required.']])
        ->get('/settings/spotify')
        ->assertInertia(fn (Assert $page) => $page
            ->where('message.tone', 'error')
            ->where('message.text', 'Spotify Premium is required.'));
});

test('Disconnecting Spotify', function () {
    $connection = SpotifyConnection::factory()->create();

    $this->actingAs($connection->user)
        ->delete('/spotify/connection')
        ->assertRedirect('/settings/spotify');

    expect(SpotifyConnection::count())->toBe(0);

    $this->get('/settings/spotify')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('status', 'not_connected')
            ->where('message.text', 'Spotify disconnected.')
            ->where('message.detail', fn (string $detail) => str_contains($detail, 'spotify.com/account/apps')));

    Http::assertNothingSent();
});

test('Disconnecting without a connection', function () {
    $this->actingAs(User::factory()->create())
        ->delete('/spotify/connection')
        ->assertRedirect('/settings/spotify');

    $this->get('/settings/spotify')->assertOk();
});

test('disconnecting needs a session', function () {
    SpotifyConnection::factory()->create();

    $this->delete('/spotify/connection')->assertRedirect('/login');

    expect(SpotifyConnection::count())->toBe(1);
});

test('the settings page needs a session', function () {
    $this->get('/settings/spotify')->assertRedirect('/login');
});

test('Shared status for each connection state', function () {
    $owner = User::factory()->create();

    $shared = fn (string $status, bool $connected) => $this->actingAs($owner)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('spotify.status', $status)
            ->where('spotify.connected', $connected));

    $shared('not_connected', false);

    $connection = SpotifyConnection::factory()->for($owner)->create();
    $shared('connected', true);

    $connection->update(['status' => 'reconnect_required', 'access_token' => null, 'refresh_token' => null]);
    $shared('reconnect_required', false);
});

test('guests share not_connected', function () {
    SpotifyConnection::factory()->create();

    $this->get('/login')->assertInertia(fn (Assert $page) => $page
        ->where('spotify.status', 'not_connected')
        ->where('spotify.connected', false));
});

test('The settings page props hold no tokens', function () {
    $connection = SpotifyConnection::factory()->create();

    $response = $this->actingAs($connection->user)->get('/settings/spotify');

    $page = json_encode($response->viewData('page'));

    expect($page)->toContain('Alice')
        ->not->toContain('BQ-access-123')
        ->not->toContain('AQ-refresh-456');

    $response->assertDontSee('BQ-access-123')->assertDontSee('AQ-refresh-456');
});
