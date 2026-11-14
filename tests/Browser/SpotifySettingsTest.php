<?php

// spotify-connection, task 3.5: "The Spotify settings page uses the Aero look", with the shared focus walk and
// 375 px check from tests/Pest.php.

use App\Models\SpotifyConnection;
use App\Models\User;

beforeEach(function () {
    config()->set('services.spotify', [
        'client_id' => 'client-abc',
        'client_secret' => 'secret-xyz',
        'owner_id' => 'owner-123',
        'redirect_uri' => 'http://127.0.0.1:8000/spotify/callback',
        'timeout' => 10,
    ]);
});

/**
 * Signs the owner in, gives the owner a Spotify connection with the given attributes and opens the settings page.
 */
function ownerSpotifySettings(array $attributes = [])
{
    $page = signedInOwnerVisit('/dashboard');

    SpotifyConnection::factory()->for(User::sole())->create($attributes);

    return $page->navigate('/settings/spotify');
}

test('The settings page is Aero', function () {
    $page = signedInOwnerVisit('/settings/spotify')->assertSee('Connect Spotify');

    expect($page->script('document.querySelectorAll("[data-aero=background]").length'))->toBe(1)
        ->and($page->script('document.querySelectorAll("[data-aero=glass-panel]").length'))->toBe(1)
        ->and($page->script('document.querySelectorAll("[data-slot]").length'))->toBe(0)
        ->and($page->script('document.querySelector("#spotify-connect").closest("form").getAttribute("method")'))->toBe('get');
});

test('The settings page shows keyboard focus', function () {
    // A connection missing a scope shows both buttons, so the walk covers every control the page can have.
    $page = ownerSpotifySettings(['scopes' => 'streaming'])
        ->assertSee('Spotify connected')
        ->assertSee('Connect Spotify')
        ->assertSee('Disconnect Spotify');

    // The walk starts on <main>, before the "Back" link.
    aeroAssertFocusWalk($page, '#spotify-settings');
});

test('The settings page fits a 375 px phone', function () {
    $page = ownerSpotifySettings(['scopes' => 'streaming'])->resize(375, 667)->assertSee('Disconnect Spotify');

    expect($page->script(AERO_MOBILE_PROBLEMS))->toBe([]);
});

test('disconnecting from the page shows the message and the connect button', function () {
    ownerSpotifySettings()
        ->click('#spotify-disconnect')
        ->wait(2)
        ->assertSee('Spotify disconnected.')
        ->assertSee('spotify.com/account/apps')
        ->assertSee('Connect Spotify')
        ->assertDontSee('Disconnect Spotify');

    expect(SpotifyConnection::count())->toBe(0);
});
