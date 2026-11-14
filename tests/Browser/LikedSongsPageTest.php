<?php

// spotify-library-tracks, tasks 4.1 and 4.2: the Liked Songs page in a real browser (design D5, D6). Spotify is faked
// on the server, and the playing scenarios use the fake SDK of spotify-web-player. Helper names carry a
// "likedBrowser" prefix because Pest declares them globally.

use App\Models\SpotifyConnection;
use App\Models\User;
use App\Spotify\SpotifyScopes;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

const LIKED_BROWSER_SDK = '/testing/liked-fake-spotify-sdk.js';

beforeEach(function () {
    Http::preventStrayRequests();

    Route::get(LIKED_BROWSER_SDK, fn () => response()->file(
        base_path('tests/Browser/fixtures/fake-spotify-sdk.js'),
        ['Content-Type' => 'application/javascript'],
    ));

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
 * The fixture's saved songs: Song X, Song Y and Song Z, newest first.
 *
 * @return list<array<string, mixed>>
 */
function likedBrowserEntries(): array
{
    return json_decode(file_get_contents(base_path('tests/Fixtures/spotify/me-tracks.json')), true)['items'];
}

/**
 * Fake the saved songs, $entries or $total generated ones, 50 per page; $failNext answers the second page with 503
 * while it is true. Also the library overview and the player's start request.
 *
 * @param  list<array<string, mixed>>|null  $entries
 */
function likedBrowserFake(?array $entries = null, ?int $total = null, bool &$failNext = false): void
{
    $template = likedBrowserEntries()[0];

    Http::fake([
        'api.spotify.com/v1/me/player/play*' => Http::response(null, 204),
        'api.spotify.com/v1/me/playlists*' => Http::response(['items' => [], 'next' => null, 'total' => 0]),
        'api.spotify.com/v1/me/tracks*' => function (Request $request) use ($entries, $total, $template, &$failNext) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $offset = (int) ($query['offset'] ?? 0);
            $limit = (int) $query['limit'];

            if ($offset > 0 && $failNext) {
                return Http::response([], 503);
            }

            $count = $total ?? count($entries ?? []);
            $page = $entries !== null
                ? array_slice($entries, $offset, $limit)
                : array_map(function (int $i) use ($template) {
                    $id = sprintf('lk%020d', $i);

                    return [...$template, 'track' => [...$template['track'], 'id' => $id, 'uri' => "spotify:track:{$id}", 'name' => "Saved {$i}"]];
                }, $count > $offset ? range($offset, min($offset + $limit, $count) - 1) : []);

            return Http::response([
                'items' => $page,
                'next' => $offset + $limit < $count ? 'https://api.spotify.com/v1/me/tracks?offset='.($offset + $limit) : null,
                'offset' => $offset,
                'total' => $count,
            ]);
        },
    ]);
}

/**
 * Sign the owner in and open $path. With $player, the fake SDK loads and its device "web-1" is ready.
 */
function likedBrowserOwner(string $path = '/library/liked', bool $player = false, array $attributes = [])
{
    if ($player) {
        config()->set('services.spotify.sdk_url', LIKED_BROWSER_SDK);
    }

    $owner = User::factory()->create(['email' => 'owner@example.com', 'password' => 'Correct-Horse-42']);
    SpotifyConnection::factory()->for($owner)->create(['access_token_expires_at' => now()->addHour(), ...$attributes]);

    $page = visit('/login')
        ->fill('email', 'owner@example.com')
        ->fill('password', 'Correct-Horse-42')
        ->press('Log in')
        ->wait(2)
        ->navigate($path)
        ->wait(1);

    if ($player) {
        $page->wait(1);
        $page->script('window.__fakeSpotify.emit("ready", {device_id: "web-1"})');
    }

    return $page;
}

/**
 * @return list<array<string, mixed>>
 */
function likedBrowserPlays(): array
{
    return Http::recorded()
        ->filter(fn (array $pair) => $pair[0]->method() === 'PUT' && str_contains($pair[0]->url(), '/v1/me/player/play'))
        ->map(fn (array $pair) => $pair[0]->data())
        ->values()
        ->all();
}

function likedBrowserRows($page): int
{
    return (int) $page->script('document.querySelectorAll("#library-liked-page [data-part=song-row]").length');
}

function likedBrowserPress($page, string $title): void
{
    $page->script('[...document.querySelectorAll("[data-part=song-row]")].find((row) => row.querySelector("[data-part=title]").textContent.trim() === '.json_encode($title).').click()');
}

// --- The owner opens Liked Songs from the library (task 4.1) -----------------------------------------------------

test('Opening Liked Songs from the library', function () {
    likedBrowserFake(likedBrowserEntries());

    $page = likedBrowserOwner('/library')->assertSee('Liked Songs');

    $page->click('[data-part=liked-entry]')->wait(2)->assertPathIs('/library/liked')->assertSee('Song X');

    expect($page->script('document.querySelectorAll("[data-slot]").length'))->toBe(0);
});

test('The pages are usable with the keyboard and on a phone: Liked Songs', function () {
    likedBrowserFake(total: 60);

    $page = likedBrowserOwner()->assertSee('Load more');

    aeroAssertFocusWalk($page, '#library-liked-page');

    $page->resize(375, 667)->wait(1);

    expect($page->script(AERO_MOBILE_PROBLEMS))->toBe([]);
});

// --- The Liked Songs page lists saved songs 50 at a time ---------------------------------------------------------

test('Seeing the saved songs', function () {
    likedBrowserFake(likedBrowserEntries());

    $page = likedBrowserOwner()->assertSee('Song Z')->assertSee('3 songs');

    expect($page->script('[...document.querySelectorAll("[data-part=song-row] [data-part=title]")].map((el) => el.textContent.trim())'))
        ->toBe(['Song X', 'Song Y', 'Song Z'])
        ->and($page->script('[...document.querySelectorAll("[data-part=song-row] [data-part=source]")].map((el) => el.textContent.trim())'))
        ->toBe(['Spotify', 'Spotify', 'Spotify']);
});

test('Thousands of saved songs', function () {
    likedBrowserFake(total: 3200);

    $page = likedBrowserOwner()->assertSee('3,200 songs');

    expect(likedBrowserRows($page))->toBe(50);

    $page->click('#library-liked-page [data-part=load-more]')->wait(1)->assertSee('Saved 99');

    expect(likedBrowserRows($page))->toBe(100);
});

test('Loading more fails', function () {
    $fail = true;
    likedBrowserFake(total: 120, failNext: $fail);

    $page = likedBrowserOwner()->assertSee('Saved 49');

    $page->click('#library-liked-page [data-part=load-more]')->wait(1)
        ->assertSee("Couldn't load more songs. Try again in a moment.");

    expect(likedBrowserRows($page))->toBe(50)
        ->and($page->script('document.querySelector("#library-liked-page [data-part=load-more]").disabled'))->toBeFalse();

    $fail = false;

    $page->click('#library-liked-page [data-part=load-more]')->wait(1)
        ->assertSee('Saved 99')
        ->assertDontSee("Couldn't load more songs.");
});

// --- Liked Songs says when it is empty or lacks permission -------------------------------------------------------

test('No saved songs', function () {
    likedBrowserFake([]);

    likedBrowserOwner()->assertSee('No liked songs yet.');
});

test('The saved-songs permission is missing on Liked Songs', function () {
    Http::fake();

    $page = likedBrowserOwner(attributes: ['scopes' => implode(' ', array_diff(SpotifyScopes::REQUIRED, ['user-library-read']))])
        ->assertSee('Reconnect Spotify to grant access to your library.')
        ->assertSee('Reconnect Spotify');

    expect($page->script('document.querySelector("#library-reconnect-liked").closest("form").getAttribute("action")'))->toEndWith('/spotify/connect');

    Http::assertNothingSent();
});

// --- Saved songs play as their page (task 4.2) -------------------------------------------------------------------

test('Playing a saved song', function () {
    likedBrowserFake(likedBrowserEntries());

    $page = likedBrowserOwner(player: true)->assertSee('Song Y');
    likedBrowserPress($page, 'Song Y');
    $page->wait(1);

    expect(likedBrowserPlays())->toBe([[
        'uris' => ['spotify:track:3n3Ppam7vgaVa1iaRUc9Lp', 'spotify:track:0VjIjW4GlUZAMYd2vXMi3b', 'spotify:track:6habFhsOp2NvshLv26DqMb'],
        'offset' => ['position' => 1],
    ]]);
});

test('Songs that cannot play are not sent', function () {
    $entries = likedBrowserEntries();
    $entries[1]['track']['name'] = 'Gone Song';
    $entries[1]['track']['is_playable'] = false;
    likedBrowserFake($entries);

    $page = likedBrowserOwner(player: true)->assertSee('Gone Song');
    likedBrowserPress($page, 'Song Z');
    $page->wait(1);

    expect(likedBrowserPlays())->toBe([[
        'uris' => ['spotify:track:3n3Ppam7vgaVa1iaRUc9Lp', 'spotify:track:6habFhsOp2NvshLv26DqMb'],
        'offset' => ['position' => 1],
    ]]);
});
