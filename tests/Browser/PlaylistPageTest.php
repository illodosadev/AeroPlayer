<?php

// spotify-library-tracks, tasks 4.1 and 4.2: the playlist page in a real browser (design D5, D6). Spotify is faked on
// the server, and the playing scenarios use the fake SDK of spotify-web-player: the start request is checked where
// it reaches Spotify. Helper names carry a "playlistBrowser" prefix because Pest declares them globally.

use App\Models\SpotifyConnection;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

const PLAYLIST_BROWSER_ID = '3cEYpjA9oz9GiPac4AsH4n';
const PLAYLIST_BROWSER_URL = '/library/playlists/spotify/'.PLAYLIST_BROWSER_ID;
const PLAYLIST_BROWSER_SDK = '/testing/playlist-fake-spotify-sdk.js';

beforeEach(function () {
    Http::preventStrayRequests();

    Route::get(PLAYLIST_BROWSER_SDK, fn () => response()->file(
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
 * @return array<string, mixed>
 */
function playlistBrowserFixture(string $name): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/spotify/{$name}.json")), true);
}

/**
 * The fixture's entries: Song A, Song B, Song C, My Demo (local), Gone Song (not playable), a deleted song and an
 * episode.
 *
 * @return list<array<string, mixed>|null>
 */
function playlistBrowserEntries(): array
{
    return playlistBrowserFixture('playlist-items')['items'];
}

/**
 * $count generated song entries from position $from.
 *
 * @return list<array<string, mixed>>
 */
function playlistBrowserGenerated(int $from, int $count): array
{
    $template = playlistBrowserEntries()[0];

    return array_map(function (int $position) use ($template) {
        $id = sprintf('sg%020d', $position);

        return [...$template, 'item' => [...$template['item'], 'id' => $id, 'uri' => "spotify:track:{$id}", 'name' => "Song {$position}"]];
    }, range($from, $from + $count - 1));
}

/**
 * Fake the playlist "Road Trip" (own unless $playlist overrides it), its entries 50 per page, the library overview
 * and the player's start request.
 *
 * @param  list<array<string, mixed>|null>  $entries
 * @param  array<string, mixed>  $playlist
 */
function playlistBrowserFake(array $entries, array $playlist = [], int $playStatus = 204, ?int $metaStatus = null): void
{
    $meta = [...playlistBrowserFixture('playlist'), 'items' => ['total' => count($entries)], ...$playlist];

    Http::fake([
        'api.spotify.com/v1/me/player/play*' => Http::response(null, $playStatus),
        'api.spotify.com/v1/me/playlists*' => Http::response(playlistBrowserFixture('me-playlists')),
        'api.spotify.com/v1/me/tracks*' => Http::response(['items' => [], 'next' => null, 'total' => 3]),
        'api.spotify.com/v1/playlists/*/items*' => function (Request $request) use ($entries) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $offset = (int) $query['offset'];

            return Http::response([
                'items' => array_slice($entries, $offset, 50),
                'next' => $offset + 50 < count($entries) ? 'https://api.spotify.com/v1/playlists/x/items?offset='.($offset + 50) : null,
                'offset' => $offset,
                'total' => count($entries),
            ]);
        },
        'api.spotify.com/v1/playlists/*' => $metaStatus === null ? Http::response($meta) : Http::response([], $metaStatus),
    ]);
}

/**
 * Sign the owner in and open $path. With $player, the fake SDK loads and its device "web-1" is ready.
 */
function playlistBrowserOwner(string $path = PLAYLIST_BROWSER_URL, bool $player = false)
{
    if ($player) {
        config()->set('services.spotify.sdk_url', PLAYLIST_BROWSER_SDK);
    }

    $owner = User::factory()->create(['email' => 'owner@example.com', 'password' => 'Correct-Horse-42']);
    SpotifyConnection::factory()->for($owner)->create(['access_token_expires_at' => now()->addHour()]);

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
 * The start requests that reached Spotify, as their JSON bodies.
 *
 * @return list<array<string, mixed>>
 */
function playlistBrowserPlays(): array
{
    return Http::recorded()
        ->filter(fn (array $pair) => $pair[0]->method() === 'PUT' && str_contains($pair[0]->url(), '/v1/me/player/play'))
        ->map(fn (array $pair) => $pair[0]->data())
        ->values()
        ->all();
}

/**
 * The offsets of the requests for the playlist's entries.
 *
 * @return list<string>
 */
function playlistBrowserItemOffsets(): array
{
    return Http::recorded()
        ->filter(fn (array $pair) => str_contains($pair[0]->url(), '/items'))
        ->map(function (array $pair) {
            parse_str((string) parse_url($pair[0]->url(), PHP_URL_QUERY), $query);

            return $query['offset'];
        })
        ->values()
        ->all();
}

function playlistBrowserRows($page): int
{
    return (int) $page->script('document.querySelectorAll("#library-playlist-page [data-part=song-row]").length');
}

/**
 * Press the song row with the given title.
 */
function playlistBrowserPress($page, string $title): void
{
    $page->script('[...document.querySelectorAll("[data-part=song-row]")].find((row) => row.querySelector("[data-part=title]").textContent.trim() === '.json_encode($title).').click()');
}

// --- The owner opens a playlist from the library (task 4.1) ------------------------------------------------------

test('Opening a playlist from the library', function () {
    playlistBrowserFake(array_slice(playlistBrowserEntries(), 0, 3));

    $page = playlistBrowserOwner('/library')->assertSee('Road Trip');

    $page->click('[data-part=playlist-row][data-id="'.PLAYLIST_BROWSER_ID.'"]')->wait(2)
        ->assertPathIs(PLAYLIST_BROWSER_URL)
        ->assertSee('Song A');

    expect($page->script('document.querySelector("#library-playlist-title").textContent.trim()'))->toBe('Road Trip')
        ->and($page->script('document.querySelectorAll("[data-slot]").length'))->toBe(0);
});

test('The pages are usable with the keyboard and on a phone: a playlist page', function () {
    playlistBrowserFake(playlistBrowserGenerated(0, 60), ['name' => 'A playlist with a very long name that has to wrap on a phone screen']);

    $page = playlistBrowserOwner()->assertSee('Load more');

    aeroAssertFocusWalk($page, '#library-playlist-page');

    $page->resize(375, 667)->wait(1);

    expect($page->script(AERO_MOBILE_PROBLEMS))->toBe([]);
});

// --- A playlist page lists its songs 50 at a time ----------------------------------------------------------------

test('Seeing the songs of an own playlist', function () {
    playlistBrowserFake(array_slice(playlistBrowserEntries(), 0, 3));

    $page = playlistBrowserOwner()->assertSee('Song C')->assertSee('3 songs');

    expect($page->script('[...document.querySelectorAll("[data-part=song-row] [data-part=title]")].map((el) => el.textContent.trim())'))
        ->toBe(['Song A', 'Song B', 'Song C'])
        ->and($page->script('[...document.querySelectorAll("[data-part=song-row] [data-part=duration]")].map((el) => el.textContent.trim())'))
        ->toBe(['3:45', '4:02', '2:58'])
        ->and($page->script('[...document.querySelectorAll("[data-part=song-row] [data-part=source]")].map((el) => el.textContent.trim())'))
        ->toBe(['Spotify', 'Spotify', 'Spotify']);
});

test('A playlist with many songs', function () {
    playlistBrowserFake(playlistBrowserGenerated(0, 230));

    $page = playlistBrowserOwner()->assertSee('230 songs');

    expect(playlistBrowserRows($page))->toBe(50);

    $page->click('#library-playlist-page [data-part=load-more]')->wait(1)->assertSee('Song 99');

    expect(playlistBrowserRows($page))->toBe(100)
        ->and(playlistBrowserItemOffsets())->toBe(['0', '50']);
});

test('A double click loads one page', function () {
    playlistBrowserFake(playlistBrowserGenerated(0, 230));

    $page = playlistBrowserOwner()->assertSee('Song 49');

    // Two clicks in the same task: the second lands before the button re-renders as disabled.
    $page->script('(() => { const more = document.querySelector("#library-playlist-page [data-part=load-more]"); more.click(); more.click(); })()');
    $page->wait(1)->assertSee('Song 99');

    expect(playlistBrowserItemOffsets())->toBe(['0', '50'])
        ->and(playlistBrowserRows($page))->toBe(100);
});

// --- Followed, empty and unavailable playlists -------------------------------------------------------------------

test('Opening a followed playlist', function () {
    playlistBrowserFake([], [
        'id' => '7dKbC1VnGzXSFqY2cVvRmb',
        'uri' => 'spotify:playlist:indie',
        'name' => 'Indie Picks',
        'owner' => ['id' => 'alice-789', 'display_name' => 'Alice'],
        'items' => null,
    ]);

    $page = playlistBrowserOwner('/library/playlists/spotify/7dKbC1VnGzXSFqY2cVvRmb')
        ->assertSee("Spotify doesn't let this app list the songs of playlists you don't own.")
        ->assertSee('by Alice');

    expect($page->script('document.querySelector("#library-play").disabled'))->toBeFalse()
        ->and(playlistBrowserItemOffsets())->toBe([]);
});

test('An empty playlist', function () {
    playlistBrowserFake([], ['name' => 'New Ideas']);

    $page = playlistBrowserOwner()->assertSee('This playlist is empty.');

    expect($page->script('document.querySelector("#library-play").disabled'))->toBeTrue();
});

test('A playlist that is no longer available', function () {
    playlistBrowserFake([], metaStatus: 404);

    $page = playlistBrowserOwner()->assertSee('This playlist is no longer available.');

    expect($page->script('document.querySelector("[data-part=back-to-library]").getAttribute("href")'))->toBe('/library');

    $page->click('[data-part=back-to-library]')->wait(2)->assertPathIs('/library');
});

// --- Playlist songs play in the playlist's context (task 4.2) ----------------------------------------------------

test('Playing a playlist from the start', function () {
    playlistBrowserFake(array_slice(playlistBrowserEntries(), 0, 3), ['uri' => 'spotify:playlist:road']);

    $page = playlistBrowserOwner(player: true)->assertSee('Song A');
    $page->click('#library-play')->wait(1);

    expect(playlistBrowserPlays())->toBe([['context_uri' => 'spotify:playlist:road', 'offset' => ['position' => 0]]]);
});

test('Playing a playlist from a song', function () {
    playlistBrowserFake(array_slice(playlistBrowserEntries(), 0, 3), ['uri' => 'spotify:playlist:road']);

    $page = playlistBrowserOwner(player: true)->assertSee('Song B');
    playlistBrowserPress($page, 'Song B');
    $page->wait(1);

    expect(playlistBrowserPlays())->toBe([['context_uri' => 'spotify:playlist:road', 'offset' => ['position' => 1]]]);
});

test('The position counts hidden entries', function () {
    $entries = playlistBrowserEntries();
    playlistBrowserFake([$entries[5], $entries[1]], ['uri' => 'spotify:playlist:road']);

    $page = playlistBrowserOwner(player: true)->assertSee('Song B');

    expect(playlistBrowserRows($page))->toBe(1);

    playlistBrowserPress($page, 'Song B');
    $page->wait(1);

    expect(playlistBrowserPlays())->toBe([['context_uri' => 'spotify:playlist:road', 'offset' => ['position' => 1]]]);
});

test('Playing a followed playlist', function () {
    playlistBrowserFake([], [
        'id' => '7dKbC1VnGzXSFqY2cVvRmb',
        'uri' => 'spotify:playlist:indie',
        'name' => 'Indie Picks',
        'owner' => ['id' => 'alice-789', 'display_name' => 'Alice'],
        'items' => null,
    ]);

    $page = playlistBrowserOwner('/library/playlists/spotify/7dKbC1VnGzXSFqY2cVvRmb', player: true)->assertSee('Indie Picks');
    $page->click('#library-play')->wait(1);

    expect(playlistBrowserPlays())->toBe([['context_uri' => 'spotify:playlist:indie', 'offset' => ['position' => 0]]]);
});

test('A local file in a playlist and A song that is not available', function () {
    playlistBrowserFake(playlistBrowserEntries());

    $page = playlistBrowserOwner(player: true)->assertSee('My Demo')->assertSee('Gone Song');

    $reasons = $page->script('Object.fromEntries([...document.querySelectorAll("[data-part=song-row]:disabled")].map((row) => [row.querySelector("[data-part=title]").textContent.trim(), row.querySelector("[data-part=not-available]").textContent.trim()]))');

    expect($reasons)->toBe(['My Demo' => "Local file, can't play here", 'Gone Song' => 'Not available']);

    // Playwright's click() waits for an enabled element, so the disabled rows are pressed with a script (design D6).
    playlistBrowserPress($page, 'My Demo');
    playlistBrowserPress($page, 'Gone Song');
    $page->wait(1);

    expect(playlistBrowserPlays())->toBe([]);
});

test('Playback cannot start', function () {
    playlistBrowserFake(array_slice(playlistBrowserEntries(), 0, 3), playStatus: 503);

    $page = playlistBrowserOwner(player: true)->assertSee('Song A');
    $page->click('#library-play')->wait(1)
        ->assertSee("Playback couldn't start. Try again in a moment.")
        ->assertSee('Song C');

    expect(playlistBrowserPlays())->toHaveCount(1);
});
