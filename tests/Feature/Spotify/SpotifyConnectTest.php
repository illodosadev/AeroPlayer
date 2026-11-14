<?php

// spotify-connection, task 2.2: "Connecting starts Spotify authorization", "Spotify must be configured to connect"
// and "The settings entry point leads to the Spotify settings".

use App\Models\User;
use Illuminate\Support\Facades\Http;

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

test('Starting the connection redirects to Spotify with the scopes and a state', function () {
    $response = $this->actingAs(User::factory()->create())->get('/spotify/connect');

    $response->assertRedirect();
    $location = $response->headers->get('Location');

    expect(strtok($location, '?'))->toBe('https://accounts.spotify.com/authorize');

    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($query['response_type'])->toBe('code')
        ->and($query['client_id'])->toBe('client-abc')
        ->and($query['redirect_uri'])->toBe('http://127.0.0.1:8000/spotify/callback')
        ->and($query['show_dialog'])->toBe('true')
        ->and(explode(' ', $query['scope']))->toEqualCanonicalizing([
            'streaming',
            'user-read-email',
            'user-read-private',
            'user-modify-playback-state',
            'user-read-playback-state',
            'user-read-currently-playing',
            'playlist-read-private',
            'playlist-read-collaborative',
            'user-library-read',
        ])
        ->and(strlen($query['state']))->toBeGreaterThanOrEqual(40)
        ->and($query['state'])->toBe(session('spotify_oauth_state'));

    Http::assertNothingSent();
});

test('each connection attempt gets a new state', function () {
    $this->actingAs(User::factory()->create())->get('/spotify/connect');
    $first = session('spotify_oauth_state');

    $this->get('/spotify/connect');

    expect(session('spotify_oauth_state'))->not->toBe($first);
});

test('A Spotify setting is missing', function (string $setting) {
    config()->set("services.spotify.{$setting}", '');

    $this->actingAs(User::factory()->create())
        ->get('/spotify/connect')
        ->assertRedirect('/settings/spotify');

    expect(session('spotify_oauth_state'))->toBeNull();
    Http::assertNothingSent();
})->with([
    'SPOTIFY_CLIENT_ID' => 'client_id',
    'SPOTIFY_CLIENT_SECRET' => 'client_secret',
    'SPOTIFY_OWNER_ID' => 'owner_id',
]);

test('the redirect URI defaults to the app callback URL', function () {
    config()->set('services.spotify.redirect_uri', null);

    $location = $this->actingAs(User::factory()->create())->get('/spotify/connect')->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($query['redirect_uri'])->toBe(url('/spotify/callback'));
});

test('Starting the connection without a session goes to the login', function () {
    $this->get('/spotify/connect')->assertRedirect('/login');

    Http::assertNothingSent();
});

test('Opening the settings', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings')
        ->assertRedirect('/settings/spotify');
});

test('the settings entry point needs a session', function () {
    $this->get('/settings')->assertRedirect('/login');
});
