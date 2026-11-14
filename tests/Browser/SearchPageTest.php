<?php

// spotify-search-page: the search page in a real browser (design D6). Spotify is faked on the server; the browser
// server runs in the test process, so Http::recorded() counts what reached Spotify. Helper names carry a "searchPage"
// prefix because Pest declares them globally.

use App\Models\SpotifyConnection;
use App\Models\User;
use App\Spotify\SpotifyOAuth;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

const SEARCH_PAGE_API_URL = 'https://api.spotify.com/v1/search';
const SEARCH_PAGE_FAKE_SDK_PATH = '/testing/search-fake-spotify-sdk.js';

beforeEach(function () {
    Http::preventStrayRequests();

    Route::get(SEARCH_PAGE_FAKE_SDK_PATH, fn () => response()->file(
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
 * Spotify's trimmed search answer for "Daft Punk": 3 tracks, 2 albums and 1 artist (spotify-search-api, design D5).
 *
 * @return array<string, mixed>
 */
function searchPageFixture(): array
{
    return json_decode(file_get_contents(base_path('tests/Fixtures/spotify/search-daft-punk.json')), true);
}

/**
 * A Spotify paging object with the given items.
 *
 * @param  list<mixed>  $items
 * @return array<string, mixed>
 */
function searchPagePaging(array $items, int $offset = 0, bool $hasNext = false, int $total = 0): array
{
    return [
        'href' => SEARCH_PAGE_API_URL."?offset={$offset}&limit=10",
        'limit' => 10,
        'next' => $hasNext ? SEARCH_PAGE_API_URL.'?offset='.($offset + 10).'&limit=10' : null,
        'offset' => $offset,
        'previous' => null,
        'total' => $total ?: count($items),
        'items' => $items,
    ];
}

/**
 * Tracks from position $from to $to - 1, built over the fixture's first track with their own ids. $ids replaces the
 * id of some positions.
 *
 * @param  array<int, string>  $ids
 * @return array<string, mixed>
 */
function searchPageTracks(int $from, int $to, bool $hasNext, array $ids = []): array
{
    $template = searchPageFixture()['tracks']['items'][0];
    $items = [];

    for ($position = $from; $position < $to; $position++) {
        $id = $ids[$position] ?? sprintf('trk%019d', $position);
        $items[] = [...$template, 'id' => $id, 'uri' => "spotify:track:{$id}", 'name' => "Track {$position}"];
    }

    return searchPagePaging($items, $from, $hasNext, 25);
}

/**
 * The decoded query string of a faked request.
 *
 * @return array<string, string>
 */
function searchPageQuery(Request $request): array
{
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

    return $query;
}

/**
 * The searches that reached Spotify, as their decoded query strings.
 *
 * @return list<array<string, string>>
 */
function searchPageSent(): array
{
    return Http::recorded()
        ->filter(fn (array $pair) => strtok($pair[0]->url(), '?') === SEARCH_PAGE_API_URL)
        ->map(fn (array $pair) => searchPageQuery($pair[0]))
        ->values()
        ->all();
}

/**
 * Fake Spotify's search with a callback that receives the decoded query and returns the answer, and the player's
 * start request with the given status.
 */
function searchPageFake(Closure $answer, int $playStatus = 204): void
{
    Http::fake([
        'api.spotify.com/v1/search*' => fn (Request $request) => $answer(searchPageQuery($request)),
        'api.spotify.com/v1/me/player/play*' => Http::response(null, $playStatus),
    ]);
}

/**
 * Sign the owner in, with a Spotify connection unless $connected is false, and open the search page.
 */
function searchPageOwner(bool $connected = true, array $attributes = [])
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
        ->navigate('/search')
        ->wait(1);
}

/**
 * Type a query into the field as one input event and wait past the debounce.
 */
function searchPageSearch($page, string $query, float $wait = 1)
{
    return $page->fill('#search-query', $query)->wait($wait);
}

/**
 * The number of rows a section shows.
 */
function searchPageRows($page, string $section): int
{
    return (int) $page->script('document.querySelectorAll("[data-section='.$section.'] li").length');
}

/**
 * The text of the first element matching a selector, or null.
 */
function searchPageText($page, string $selector): ?string
{
    return $page->script('document.querySelector('.json_encode($selector).')?.textContent.trim() ?? null');
}

// --- The owner has a search page (task 3.1) ----------------------------------------------------------------------

test('Opening the search page', function () {
    Http::fake();

    $page = searchPageOwner()
        ->navigate('/dashboard')
        ->wait(1)
        ->click('a[href="/search"]')
        ->wait(2)
        ->assertPathIs('/search')
        ->assertSee('Search songs, albums and artists')
        ->assertSee('Type to search your music.');

    expect($page->script('document.querySelectorAll("#search-page [data-aero=glass-panel]").length'))->toBe(1)
        ->and($page->script('document.querySelectorAll("[data-slot]").length'))->toBe(0)
        ->and($page->script('document.querySelector("#search-query").getAttribute("maxlength")'))->toBe('200');

    Http::assertNothingSent();
});

test('The page is usable with the keyboard and on a phone', function () {
    searchPageFake(fn () => Http::response([
        ...searchPageFixture(),
        'tracks' => searchPageTracks(0, 3, true),
    ]));

    $page = searchPageSearch(searchPageOwner(), 'Daft Punk')->assertSee('Load more');

    // The walk starts on <main>, before the "Back" link, and covers the field, the songs and "Load more".
    aeroAssertFocusWalk($page, '#search-page');

    $page->resize(375, 667)->wait(1);

    expect($page->script(AERO_MOBILE_PROBLEMS))->toBe([]);
});

// --- Results show in songs, albums and artists sections ----------------------------------------------------------

test('Searching Spotify from the page', function () {
    searchPageFake(fn () => Http::response(searchPageFixture()));

    $page = searchPageSearch(searchPageOwner(), 'Daft Punk')->assertSee('Songs');

    expect(searchPageRows($page, 'songs'))->toBe(3)
        ->and(searchPageRows($page, 'albums'))->toBe(2)
        ->and(searchPageRows($page, 'artists'))->toBe(1)
        ->and($page->script('[...document.querySelectorAll("[data-section=songs] [data-id]")].map((row) => row.dataset.id)'))
        ->toBe(['0DiWol3AO6WpXZgp0goxAV', '5W3cjX2J3tjhG8zb6u0qHn', '1pKYYY0dkg23sQQXi0Q5zN'])
        ->and($page->script('[...document.querySelectorAll("[data-section] h2")].map((h) => h.textContent.trim())'))
        ->toBe(['Songs', 'Albums', 'Artists']);

    expect(searchPageSent())->toHaveCount(1)
        ->and(searchPageSent()[0])->toMatchArray(['q' => 'Daft Punk', 'type' => 'track,album,artist', 'limit' => '10', 'offset' => '0']);
});

test('No results', function () {
    searchPageFake(fn () => Http::response([
        'tracks' => searchPagePaging([]),
        'albums' => searchPagePaging([]),
        'artists' => searchPagePaging([]),
    ]));

    $page = searchPageSearch(searchPageOwner(), 'zzqxjkwvvy')->assertSee('No results for "zzqxjkwvvy"');

    expect($page->script('document.querySelectorAll("[data-section]").length'))->toBe(0);
});

test('An empty section is hidden', function () {
    searchPageFake(fn () => Http::response([...searchPageFixture(), 'artists' => searchPagePaging([])]));

    $page = searchPageSearch(searchPageOwner(), 'Daft Punk')->assertSee('Albums');

    expect($page->script('[...document.querySelectorAll("[data-section]")].filter((s) => s.offsetParent !== null).map((s) => s.dataset.section)'))
        ->toBe(['songs', 'albums']);
});

test('An expired token does not stop the search', function () {
    Http::fake([
        SpotifyOAuth::TOKEN_URL => Http::response(['access_token' => 'BQ-fresh-789', 'expires_in' => 3600]),
        'api.spotify.com/v1/search*' => Http::response(searchPageFixture()),
    ]);

    $page = searchPageOwner(attributes: ['access_token_expires_at' => now()->subMinute()]);

    searchPageSearch($page, 'Daft Punk')->assertSee('One More Time')->assertDontSee('Reconnect Spotify');
});

// --- Result rows show their details ------------------------------------------------------------------------------

test('A song row shows its details and its service', function () {
    searchPageFake(fn () => Http::response(searchPageFixture()));

    $page = searchPageSearch(searchPageOwner(), 'Daft Punk')->assertSee('One More Time');
    $row = '[data-section=songs] [data-id="0DiWol3AO6WpXZgp0goxAV"]';

    expect(searchPageText($page, "{$row} [data-part=title]"))->toBe('One More Time')
        ->and(searchPageText($page, "{$row} [data-part=artists]"))->toBe('Daft Punk')
        ->and(searchPageText($page, "{$row} [data-part=album]"))->toBe('Discovery')
        ->and(searchPageText($page, "{$row} [data-part=duration]"))->toBe('5:20')
        ->and(searchPageText($page, "{$row} [data-part=source]"))->toBe('Spotify')
        ->and($page->script('document.querySelector('.json_encode("{$row} [data-aero=cover] img").')?.getAttribute("src")'))
        ->toBe('https://i.scdn.co/image/ab67616d0000b273b33d46dfa2635a47eebf63b2');
});

test('An item without an image', function () {
    $fixture = searchPageFixture();
    $fixture['artists']['items'][0]['images'] = [];
    searchPageFake(fn () => Http::response($fixture));

    $page = searchPageSearch(searchPageOwner(), 'Daft Punk')->assertSee('Artists');
    $cover = '[data-section=artists] [data-aero=cover]';

    expect($page->script('document.querySelectorAll('.json_encode("{$cover} img").').length'))->toBe(0)
        ->and($page->script('document.querySelectorAll('.json_encode("{$cover} [data-aero=cover-fallback]").').length'))->toBe(1);
});

// --- The page asks for a Spotify connection when it lacks one ----------------------------------------------------

test('Searching without Spotify connected', function () {
    Http::fake();

    $page = searchPageOwner(connected: false)
        ->assertSee('Connect Spotify to search its catalog.')
        ->assertSee('Connect Spotify');

    expect($page->script('document.querySelectorAll("#search-query").length'))->toBe(0)
        ->and($page->script('document.querySelector("#search-connect").closest("form").getAttribute("action")'))->toEndWith('/spotify/connect');

    Http::assertNothingSent();
});

test('Spotify access was revoked', function () {
    Http::fake([SpotifyOAuth::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400)]);

    $page = searchPageOwner(attributes: ['access_token_expires_at' => now()->subMinute()]);

    searchPageSearch($page, 'Daft Punk')
        ->assertSee('Spotify access was revoked.')
        ->assertSee('Reconnect Spotify')
        ->assertPathIs('/search');

    expect(searchPageSent())->toBe([]);
});

// --- A search starts when the owner stops typing (task 3.2) ------------------------------------------------------

test('Typing quickly sends one search', function () {
    searchPageFake(fn () => Http::response(searchPageFixture()));

    // 9 keys, 100 ms apart: the 300 ms wait never runs out while typing.
    $page = searchPageOwner()->typeSlowly('#search-query', 'Daft Punk', 100);

    expect(searchPageSent())->toBe([]);

    $page->wait(1)->assertSee('One More Time');

    expect(searchPageSent())->toHaveCount(1)
        ->and(searchPageSent()[0]['q'])->toBe('Daft Punk');
});

test('Enter searches at once', function () {
    searchPageFake(fn () => Http::response(searchPageFixture()));

    $page = searchPageOwner()->fill('#search-query', 'Daft Punk')->keys('#search-query', ['Enter']);

    $page->wait(0.2);

    expect(searchPageSent())->toHaveCount(1);

    $page->wait(1)->assertSee('One More Time');

    expect(searchPageSent())->toHaveCount(1)
        ->and(searchPageSent()[0]['q'])->toBe('Daft Punk');
});

test('Surrounding spaces and an empty field', function () {
    searchPageFake(fn () => Http::response(searchPageFixture()));

    $page = searchPageSearch(searchPageOwner(), '   Daft Punk   ')->assertSee('One More Time');

    expect(searchPageSent())->toHaveCount(1)
        ->and(searchPageSent()[0]['q'])->toBe('Daft Punk');

    searchPageSearch($page, '')->assertSee('Type to search your music.')->assertDontSee('One More Time');
    searchPageSearch($page, '   ')->assertSee('Type to search your music.');

    expect(searchPageSent())->toHaveCount(1);
});

test('The field takes at most 200 characters', function () {
    Http::fake();

    $page = searchPageOwner()->typeSlowly('#search-query', str_repeat('a', 205), 1);

    expect($page->script('document.querySelector("#search-query").value.length'))->toBe(200);
});

// --- The newest search wins --------------------------------------------------------------------------------------

test('A late answer does not replace newer results', function () {
    $daft = searchPageFixture();
    $daft['tracks']['items'] = [[...$daft['tracks']['items'][0], 'id' => 'daftOnly0000000000000A', 'name' => 'Only Daft']];

    searchPageFake(fn (array $query) => Http::response($query['q'] === 'Daft' ? $daft : searchPageFixture()));

    $page = searchPageOwner();

    // Hold back the answer for "Daft" in the browser, and keep it from being cancelled, so only the check on the
    // newest search can keep it off the page.
    $page->script('(() => {
        const original = window.fetch;
        window.fetch = (input, init = {}) => {
            if (!String(input).endsWith("q=Daft")) {
                return original(input, init);
            }
            const { signal, ...rest } = init;
            return original(input, rest).then((response) => new Promise((resolve) => setTimeout(() => resolve(response), 2000)));
        };
    })()');

    searchPageSearch($page, 'Daft', 0.6);
    searchPageSearch($page, 'Daft Punk', 1)->assertSee('One More Time')->assertDontSee('Only Daft');

    $page->wait(2.5)->assertSee('One More Time')->assertDontSee('Only Daft');

    expect(array_column(searchPageSent(), 'q'))->toBe(['Daft', 'Daft Punk']);
});

// --- Each section loads more results (task 3.3) ------------------------------------------------------------------

/**
 * Fake 25 tracks for "Daft Punk", 10 per page, with $ids replacing the id of some positions. $onSecond answers the
 * second page instead, when given.
 *
 * @param  array<int, string>  $ids
 */
function searchPagePagedFake(array $ids = [], ?Closure $onSecond = null): void
{
    searchPageFake(function (array $query) use ($ids, $onSecond) {
        return match ([$query['type'], $query['offset']]) {
            ['track,album,artist', '0'] => Http::response([...searchPageFixture(), 'tracks' => searchPageTracks(0, 10, true, $ids)]),
            ['track', '10'] => $onSecond ? $onSecond() : Http::response(['tracks' => searchPageTracks(10, 20, true, $ids)]),
            ['track', '20'] => Http::response(['tracks' => searchPageTracks(20, 25, false, $ids)]),
        };
    });
}

const SEARCH_PAGE_SONGS_MORE = '[data-section=songs] [data-part=load-more]';

test('Loading more songs', function () {
    searchPagePagedFake();

    $page = searchPageSearch(searchPageOwner(), 'Daft Punk')->assertSee('Track 0');

    expect(searchPageRows($page, 'songs'))->toBe(10);

    $page->click(SEARCH_PAGE_SONGS_MORE)->wait(1)->assertSee('Track 19');

    expect(searchPageRows($page, 'songs'))->toBe(20)
        ->and(searchPageSent()[1])->toMatchArray(['q' => 'Daft Punk', 'type' => 'track', 'limit' => '10', 'offset' => '10']);

    $page->click(SEARCH_PAGE_SONGS_MORE)->wait(1)->assertSee('Track 24');

    expect(searchPageRows($page, 'songs'))->toBe(25)
        ->and($page->script('document.querySelectorAll('.json_encode(SEARCH_PAGE_SONGS_MORE).').length'))->toBe(0);
});

test('A double click sends one request', function () {
    searchPagePagedFake();

    $page = searchPageSearch(searchPageOwner(), 'Daft Punk')->assertSee('Track 0');

    // Two clicks in the same task: the second one lands before the button re-renders as disabled.
    $page->script('(() => { const more = document.querySelector('.json_encode(SEARCH_PAGE_SONGS_MORE).'); more.click(); more.click(); })()');
    $page->wait(1)->assertSee('Track 19');

    $ids = $page->script('[...document.querySelectorAll("[data-section=songs] [data-id]")].map((row) => row.dataset.id)');

    expect(array_filter(searchPageSent(), fn (array $query) => $query['offset'] === '10'))->toHaveCount(1)
        ->and($ids)->toHaveCount(20)
        ->and(array_unique($ids))->toHaveCount(20);
});

test('A song repeated across pages shows once', function () {
    searchPagePagedFake([3 => '4uLU6hMCjMI75M1A2tKUQC', 12 => '4uLU6hMCjMI75M1A2tKUQC']);

    $page = searchPageSearch(searchPageOwner(), 'Daft Punk')->assertSee('Track 0');
    $page->click(SEARCH_PAGE_SONGS_MORE)->wait(1)->assertSee('Track 19');

    expect($page->script('document.querySelectorAll(\'[data-section=songs] [data-id="4uLU6hMCjMI75M1A2tKUQC"]\').length'))->toBe(1)
        ->and(searchPageRows($page, 'songs'))->toBe(19);
});

test('Loading more fails', function () {
    $calls = 0;
    searchPagePagedFake(onSecond: function () use (&$calls) {
        return ++$calls === 1
            ? Http::response([], 503)
            : Http::response(['tracks' => searchPageTracks(10, 20, true)]);
    });

    $page = searchPageSearch(searchPageOwner(), 'Daft Punk')->assertSee('Track 0');

    $page->click(SEARCH_PAGE_SONGS_MORE)->wait(1)
        ->assertSee("Spotify search isn't available right now. Try again in a moment.");

    expect(searchPageRows($page, 'songs'))->toBe(10)
        ->and($page->script('document.querySelector('.json_encode(SEARCH_PAGE_SONGS_MORE).')?.disabled'))->toBeFalse();

    $page->click(SEARCH_PAGE_SONGS_MORE)->wait(1)
        ->assertSee('Track 19')
        ->assertDontSee("Spotify search isn't available right now.");

    expect(searchPageRows($page, 'songs'))->toBe(20);
});

test('No more results to load', function () {
    searchPagePagedFake();

    $page = searchPageSearch(searchPageOwner(), 'Daft Punk')->assertSee('Albums');

    expect($page->script('document.querySelectorAll("[data-section=albums] [data-part=load-more]").length'))->toBe(0)
        ->and($page->script('document.querySelectorAll("[data-section=artists] [data-part=load-more]").length'))->toBe(0);
});

// --- Pressing a song plays it (task 3.4) -------------------------------------------------------------------------

/**
 * The owner on the search page with the fake SDK's device "web-1" ready, showing the results for "Daft Punk" (the
 * given Spotify answer, or the fixture).
 *
 * @param  array<string, mixed>|null  $answer
 */
function searchPageWithPlayer(int $playStatus = 204, ?array $answer = null)
{
    config()->set('services.spotify.sdk_url', SEARCH_PAGE_FAKE_SDK_PATH);
    searchPageFake(fn () => Http::response($answer ?? searchPageFixture()), $playStatus);

    $page = searchPageOwner()->wait(1);
    $page->script('window.__fakeSpotify.emit("ready", {device_id: "web-1"})');

    return searchPageSearch($page, 'Daft Punk')->assertSee('One More Time');
}

/**
 * The start requests that reached Spotify.
 *
 * @return list<Request>
 */
function searchPagePlays(): array
{
    return Http::recorded()
        ->filter(fn (array $pair) => $pair[0]->method() === 'PUT' && str_contains($pair[0]->url(), '/v1/me/player/play'))
        ->map(fn (array $pair) => $pair[0])
        ->values()
        ->all();
}

test('Playing a song from the results', function () {
    $page = searchPageWithPlayer();

    $page->click('[data-section=songs] [data-id="0DiWol3AO6WpXZgp0goxAV"]')->wait(1);

    $plays = searchPagePlays();

    expect($plays)->toHaveCount(1)
        ->and($plays[0]->url())->toBe('https://api.spotify.com/v1/me/player/play?device_id=web-1')
        ->and($plays[0]->data())->toBe(['uris' => ['spotify:track:0DiWol3AO6WpXZgp0goxAV'], 'offset' => ['position' => 0]])
        ->and($plays[0]->data())->not->toHaveKey('context_uri');

    $page->assertDontSee("Playback couldn't start.");
});

test('A song that is not available', function () {
    $fixture = searchPageFixture();
    $fixture['tracks']['items'][1]['id'] = '6DCZcSspjsKoFjzjrWoCdn';
    $fixture['tracks']['items'][1]['uri'] = 'spotify:track:6DCZcSspjsKoFjzjrWoCdn';

    $page = searchPageWithPlayer(answer: $fixture)->assertSee('Not available');

    $row = '[data-section=songs] [data-id="6DCZcSspjsKoFjzjrWoCdn"]';

    expect($page->script('document.querySelector('.json_encode($row).').disabled'))->toBeTrue()
        ->and(searchPageText($page, "{$row} [data-part=not-available]"))->toBe('Not available')
        ->and($page->script('getComputedStyle(document.querySelector('.json_encode("{$row} [data-aero=cover]").')).opacity'))->toBe('0.5');

    // Playwright's click() waits for an enabled element, so the row is pressed with a script (design D6).
    $page->script('document.querySelector('.json_encode($row).').click()');
    $page->wait(1);

    expect(searchPagePlays())->toBe([]);
});

test('Playback cannot start', function () {
    $page = searchPageWithPlayer(playStatus: 503);

    $page->click('[data-section=songs] [data-id="0DiWol3AO6WpXZgp0goxAV"]')->wait(1)
        ->assertSee("Playback couldn't start. Try again in a moment.")
        ->assertSee('One More Time');

    expect(searchPagePlays())->toHaveCount(1);
});

// --- Search failures show a message instead of an error page (task 3.5) ------------------------------------------

const SEARCH_PAGE_UNAVAILABLE = "Spotify search isn't available right now. Try again in a moment.";

test('Spotify cannot answer', function (Closure $failure) {
    searchPageFake(fn (array $query) => $query['q'] === 'Justice' ? $failure() : Http::response(searchPageFixture()));

    $page = searchPageSearch(searchPageOwner(), 'Daft Punk')->assertSee('One More Time');

    searchPageSearch($page, 'Justice', 1.5)
        ->assertSee(SEARCH_PAGE_UNAVAILABLE)
        ->assertDontSee('One More Time')
        ->assertPathIs('/search');

    expect($page->script('document.querySelectorAll("[data-section]").length'))->toBe(0);
})->with([
    '429 with Retry-After: 30' => [fn () => fn () => Http::response([], 429, ['Retry-After' => '30'])],
    'Spotify answering 503' => [fn () => fn () => Http::response([], 503)],
    'no answer before the timeout' => [fn () => fn () => throw new ConnectionException('cURL error 28: Operation timed out')],
]);

test('The rate-limit pause is respected', function () {
    searchPageFake(fn (array $query) => $query['q'] === 'Daft Punk'
        ? Http::response([], 429, ['Retry-After' => '30'])
        : Http::response(searchPageFixture()));

    $page = searchPageSearch(searchPageOwner(), 'Daft Punk')->assertSee(SEARCH_PAGE_UNAVAILABLE);

    $this->travel(10)->seconds();
    searchPageSearch($page, 'Justice')->assertSee(SEARCH_PAGE_UNAVAILABLE);

    expect(array_column(searchPageSent(), 'q'))->toBe(['Daft Punk']);

    // 31 seconds after the 429, Enter on the same query is the retry.
    $this->travel(21)->seconds();
    $page->keys('#search-query', ['Enter'])->wait(1)->assertSee('One More Time')->assertDontSee(SEARCH_PAGE_UNAVAILABLE);

    expect(array_column(searchPageSent(), 'q'))->toBe(['Daft Punk', 'Justice']);
});

test('The session ended', function () {
    searchPageFake(fn () => Http::response(searchPageFixture()));

    $page = searchPageOwner();

    // End the session in the background, then search: the JSON 401 sends the owner to sign in.
    $page->script('fetch("/logout", {method: "POST", headers: {"X-XSRF-TOKEN": decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)[1])}})');
    $page->wait(1);

    searchPageSearch($page, 'Daft Punk', 2)->assertPathIs('/login');

    expect(searchPageSent())->toBe([]);
});
