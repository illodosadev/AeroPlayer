<?php

// spotify-library-overview, tasks 3.1 and 3.2: the library page in a real browser (design D4, D5). Spotify is faked
// on the server; the browser server runs in the test process, so Http::recorded() sees what reached Spotify. Helper
// names carry a "libraryPage" prefix because Pest declares them globally.

use App\Models\SpotifyConnection;
use App\Models\User;
use App\Spotify\SpotifyClient;
use App\Spotify\SpotifyOAuth;
use App\Spotify\SpotifyScopes;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    config()->set('services.spotify', [
        'client_id' => 'client-abc',
        'client_secret' => 'secret-xyz',
        'owner_id' => 'owner-123',
        'redirect_uri' => 'http://127.0.0.1:8000/spotify/callback',
        'timeout' => 10,
        'sdk_url' => '',
    ]);
});

/**
 * The fixture's playlists: "Road Trip" (own), "Indie Picks" (followed, by Alice) and "Office Jams" (collaborative,
 * without a cover).
 *
 * @return list<array<string, mixed>>
 */
function libraryPageFixture(): array
{
    return json_decode(file_get_contents(base_path('tests/Fixtures/spotify/me-playlists.json')), true)['items'];
}

/**
 * An own playlist with the given name and position, built over the fixture's first playlist.
 *
 * @return array<string, mixed>
 */
function libraryPagePlaylist(string $name, int $position): array
{
    $id = sprintf('pl%020d', $position);

    return [...libraryPageFixture()[0], 'id' => $id, 'uri' => "spotify:playlist:{$id}", 'name' => $name];
}

/**
 * Fake Spotify's library. The playlists and the total are read on every request, so a test can change them later;
 * $failPlaylists answers the playlists request with 503 while it is true.
 *
 * @param  list<array<string, mixed>>  $playlists
 */
function libraryPageFake(array &$playlists, int &$likedTotal, bool &$failPlaylists = false): void
{
    Http::fake([
        'api.spotify.com/v1/me/playlists*' => function (Request $request) use (&$playlists, &$failPlaylists) {
            if ($failPlaylists) {
                return Http::response(['error' => ['status' => 503, 'message' => 'upstream-body-detail']], 503);
            }

            return Http::response(['items' => $playlists, 'next' => null, 'total' => count($playlists)]);
        },
        'api.spotify.com/v1/me/tracks*' => function () use (&$likedTotal) {
            return Http::response(['items' => [], 'next' => null, 'total' => $likedTotal]);
        },
    ]);
}

/**
 * Sign the owner in, with a Spotify connection unless $connected is false, and open $path.
 */
function libraryPageOwner(bool $connected = true, array $attributes = [], string $path = '/library')
{
    $owner = User::factory()->create(['email' => 'owner@example.com', 'password' => 'Correct-Horse-42']);

    if ($connected) {
        SpotifyConnection::factory()->for($owner)->create(['access_token_expires_at' => now()->addHour(), ...$attributes]);
    }

    return visit('/login')
        ->fill('email', 'owner@example.com')
        ->fill('password', 'Correct-Horse-42')
        ->press('Log in')
        ->wait(2)
        ->navigate($path)
        ->wait(1);
}

/**
 * The texts of the elements matching a selector.
 *
 * @return list<string>
 */
function libraryPageTexts($page, string $selector): array
{
    return $page->script('[...document.querySelectorAll('.json_encode($selector).')].map((el) => el.textContent.trim())');
}

function libraryPageRow(string $id): string
{
    return '[data-part=playlist-row][data-id="'.$id.'"]';
}

/**
 * The granted scopes without one of them.
 */
function libraryPageScopesWithout(string $scope): string
{
    return implode(' ', array_diff(SpotifyScopes::REQUIRED, [$scope]));
}

// --- The owner has a library page (task 3.1) ---------------------------------------------------------------------

test('Opening the library', function () {
    $playlists = libraryPageFixture();
    $liked = 12;
    libraryPageFake($playlists, $liked);

    $page = libraryPageOwner(path: '/dashboard')
        ->click('a[href="/library"]')
        ->wait(2)
        ->assertPathIs('/library')
        ->assertSee('Road Trip');

    expect($page->script('document.querySelectorAll("#library-page [data-aero=glass-panel]").length'))->toBe(1)
        ->and($page->script('document.querySelectorAll("[data-slot]").length'))->toBe(0);
});

test('The page is usable with the keyboard and on a phone', function () {
    $playlists = [...libraryPageFixture(), libraryPagePlaylist('A playlist with a very long name that has to be cut short on a phone', 9)];
    $liked = 12;
    libraryPageFake($playlists, $liked);

    $page = libraryPageOwner()->assertSee('Road Trip');

    // The walk starts on <main>, before the "Back" link, and covers "Refresh".
    aeroAssertFocusWalk($page, '#library-page');

    $page->resize(375, 667)->wait(1);

    expect($page->script(AERO_MOBILE_PROBLEMS))->toBe([]);
});

// --- The library shows every playlist and the Liked Songs count --------------------------------------------------

test('Seeing the library', function () {
    $playlists = [libraryPagePlaylist('Road Trip', 0), libraryPagePlaylist('Chill', 1), libraryPagePlaylist('Gym', 2)];
    $liked = 12;
    libraryPageFake($playlists, $liked);

    $page = libraryPageOwner()->assertSee('Gym');

    expect(libraryPageTexts($page, '[data-part=playlist-row] [data-part=title]'))->toBe(['Road Trip', 'Chill', 'Gym'])
        ->and(libraryPageTexts($page, '[data-part=playlist-row] [data-part=source]'))->toBe(['Spotify', 'Spotify', 'Spotify'])
        ->and($page->script('document.querySelectorAll("[data-part=playlist-row] [data-aero=cover] img").length'))->toBe(3)
        ->and(libraryPageTexts($page, '#library-playlists-title'))->toBe(['Playlists'])
        ->and(libraryPageTexts($page, '[data-part=liked-count]'))->toBe(['12 songs'])
        ->and($page->script('document.querySelectorAll("[role=tab], [role=tablist]").length'))->toBe(0);
});

test('Large saved-song counts are grouped', function () {
    $playlists = libraryPageFixture();
    $liked = 3200;
    libraryPageFake($playlists, $liked);

    $page = libraryPageOwner()->assertSee('Liked Songs');

    expect(libraryPageTexts($page, '[data-part=liked-count]'))->toBe(['3,200 songs']);
});

// --- Playlists use the app's common format -----------------------------------------------------------------------

test('A playlist without a cover', function () {
    $playlists = [[...libraryPagePlaylist('Untitled Mix', 0), 'images' => []]];
    $liked = 3;
    libraryPageFake($playlists, $liked);

    $page = libraryPageOwner()->assertSee('Untitled Mix');
    $row = libraryPageRow(sprintf('pl%020d', 0));

    expect($page->script('document.querySelectorAll('.json_encode("{$row} [data-aero=cover] img").').length'))->toBe(0)
        ->and($page->script('document.querySelectorAll('.json_encode("{$row} [data-aero=cover-fallback]").').length'))->toBe(1);
});

test('A playlist the owner only follows', function () {
    $playlists = libraryPageFixture();
    $liked = 3;
    libraryPageFake($playlists, $liked);

    $page = libraryPageOwner()->assertSee('Indie Picks');

    expect(libraryPageTexts($page, libraryPageRow('7dKbC1VnGzXSFqY2cVvRmb').' [data-part=detail]'))->toBe(['by Alice'])
        ->and(libraryPageTexts($page, libraryPageRow('3cEYpjA9oz9GiPac4AsH4n').' [data-part=detail]'))->toBe(['3 songs']);
});

test('A collaborative playlist', function () {
    $playlists = libraryPageFixture();
    $liked = 3;
    libraryPageFake($playlists, $liked);

    $page = libraryPageOwner()->assertSee('Office Jams');

    expect(libraryPageTexts($page, libraryPageRow('1hPrq2MZSTvJdK4qXa0Nwe').' [data-part=collaborative]'))->toBe(['Collaborative'])
        ->and(libraryPageTexts($page, libraryPageRow('3cEYpjA9oz9GiPac4AsH4n').' [data-part=collaborative]'))->toBe([]);
});

// --- Empty libraries say so (task 3.2) ---------------------------------------------------------------------------

test('An empty library', function () {
    $playlists = [];
    $liked = 0;
    libraryPageFake($playlists, $liked);

    $page = libraryPageOwner()->assertSee('Your library is empty.');

    expect($page->script('document.querySelectorAll("[data-section]").length'))->toBe(0);
});

test('Saved songs but no playlists', function () {
    $playlists = [];
    $liked = 12;
    libraryPageFake($playlists, $liked);

    libraryPageOwner()
        ->assertSee('Liked Songs')
        ->assertSee('12 songs')
        ->assertSee('No playlists yet.')
        ->assertDontSee('Your library is empty.');
});

test('A missing permission is not an empty library', function () {
    $playlists = [];
    $liked = 0;
    libraryPageFake($playlists, $liked);

    libraryPageOwner(attributes: ['scopes' => libraryPageScopesWithout('playlist-read-private')])
        ->assertSee('Reconnect Spotify to grant access to your library.')
        ->assertSee('Reconnect Spotify')
        ->assertDontSee('Your library is empty.');
});

// --- The library asks for a Spotify connection when it lacks one -------------------------------------------------

test('Spotify not connected', function () {
    Http::fake();

    $page = libraryPageOwner(connected: false)
        ->assertSee('Connect Spotify to see your library.')
        ->assertSee('Connect Spotify')
        ->assertDontSee('Refresh');

    expect($page->script('document.querySelector("#library-connect").closest("form").getAttribute("action")'))->toEndWith('/spotify/connect');

    Http::assertNothingSent();
});

test('Spotify access was revoked', function () {
    Http::fake([SpotifyOAuth::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400)]);

    libraryPageOwner(attributes: ['access_token_expires_at' => now()->subMinute()])
        ->assertSee('Your Spotify access expired or was revoked. Please reconnect Spotify.')
        ->assertSee('Reconnect Spotify')
        ->assertDontSee('Connect Spotify to see your library.')
        ->assertDontSee('Liked Songs')
        ->assertPathIs('/library');
});

// --- The library asks for a missing permission -------------------------------------------------------------------

test('The saved-songs permission is missing', function () {
    $playlists = libraryPageFixture();
    $liked = 3;
    libraryPageFake($playlists, $liked);

    $page = libraryPageOwner(attributes: ['scopes' => libraryPageScopesWithout('user-library-read')])->assertSee('Road Trip');

    expect(libraryPageTexts($page, '[data-part=liked-permission]'))->toBe(['Reconnect Spotify to grant access to your library.'])
        ->and(libraryPageTexts($page, '[data-part=liked-count]'))->toBe([]);
});

test('The playlists permission is missing', function () {
    $playlists = libraryPageFixture();
    $liked = 3;
    libraryPageFake($playlists, $liked);

    $page = libraryPageOwner(attributes: ['scopes' => libraryPageScopesWithout('playlist-read-private')])
        ->assertSee('Reconnect Spotify to grant access to your library.')
        ->assertSee('3 songs')
        ->assertDontSee('Road Trip');

    expect($page->script('document.querySelectorAll("[data-part=playlists-permission] #library-reconnect-playlists").length'))->toBe(1);
});

// --- Library failures show a message instead of an error page ----------------------------------------------------

test('Spotify fails', function () {
    $playlists = libraryPageFixture();
    $liked = 3;
    $fail = true;
    libraryPageFake($playlists, $liked, $fail);

    libraryPageOwner()
        ->assertSee("Couldn't load your Spotify library.")
        ->assertSee('Try again')
        ->assertDontSee('upstream-body-detail')
        ->assertDontSee('BQ-access-123')
        ->assertPathIs('/library');
});

test('Trying again', function () {
    $playlists = libraryPageFixture();
    $liked = 3;
    $fail = true;
    libraryPageFake($playlists, $liked, $fail);

    $page = libraryPageOwner()->assertSee("Couldn't load your Spotify library.");

    $fail = false;

    $page->click('#library-try-again')->wait(2)
        ->assertSee('Road Trip')
        ->assertDontSee("Couldn't load your Spotify library.");
});

test('Spotify is busy and nothing is kept', function () {
    Http::fake();
    Cache::put(SpotifyClient::PAUSE_KEY, now()->getTimestamp() + 30, 30);

    libraryPageOwner()->assertSee('Spotify is busy right now. Try again in a moment.');

    Http::assertNothingSent();
});

// --- The library is cached for 10 minutes ------------------------------------------------------------------------

test('Refreshing the library', function () {
    $playlists = libraryPageFixture();
    $liked = 3;
    libraryPageFake($playlists, $liked);

    $page = libraryPageOwner()->assertSee('Road Trip')->assertDontSee('Brand New Mix');

    $playlists[] = libraryPagePlaylist('Brand New Mix', 3);

    // Reloading within 10 minutes reads the cache, so "Brand New Mix" is not there yet.
    $page->navigate('/library')->wait(1)->assertDontSee('Brand New Mix');

    $page->click('#library-refresh')->wait(2)->assertSee('Brand New Mix')->assertPathIs('/library');
});
