<?php

// spotify-search-api, task 2.2: every scenario of the "music-search" spec through "GET /search/results", with
// Spotify faked from the fixtures in tests/Fixtures/spotify (design D5). No test reaches Spotify.

use App\Application\Search\SearchSection;
use App\Http\Search\SearchCursor;
use App\Models\SpotifyConnection;
use App\Models\User;
use App\Spotify\SpotifyOAuth;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

const SEARCH_API_URL = 'https://api.spotify.com/v1/search';

beforeEach(function () {
    Http::preventStrayRequests();
    $this->freezeSecond();

    config()->set('services.spotify', [
        'client_id' => 'client-abc',
        'client_secret' => 'secret-xyz',
        'owner_id' => 'owner-123',
        'redirect_uri' => 'http://127.0.0.1:8000/spotify/callback',
        'timeout' => 10,
    ]);
});

/**
 * The owner with a Spotify connection whose access token expires the given seconds from now.
 */
function searchOwner(int $expiresIn = 3600): User
{
    return SpotifyConnection::factory()->create(['access_token_expires_at' => now()->addSeconds($expiresIn)])->user;
}

/**
 * Spotify's trimmed search answer for "Daft Punk": 3 tracks, 2 albums and 1 artist, each with next null.
 *
 * @return array<string, mixed>
 */
function searchFixture(): array
{
    return json_decode(file_get_contents(dirname(__DIR__, 2).'/Fixtures/spotify/search-daft-punk.json'), true);
}

/**
 * A Spotify paging object with the given items.
 *
 * @param  list<mixed>  $items
 * @return array<string, mixed>
 */
function searchPaging(array $items, int $offset = 0, bool $hasNext = false, int $total = 0): array
{
    return [
        'href' => SEARCH_API_URL."?offset={$offset}&limit=10",
        'limit' => 10,
        'next' => $hasNext ? SEARCH_API_URL.'?offset='.($offset + 10).'&limit=10' : null,
        'offset' => $offset,
        'previous' => null,
        'total' => $total ?: count($items),
        'items' => $items,
    ];
}

/**
 * Tracks from position $from to $to - 1 out of 25, built over the fixture's first track with their own ids.
 *
 * @return array<string, mixed>
 */
function searchTracksPage(int $from, int $to, bool $hasNext): array
{
    $template = searchFixture()['tracks']['items'][0];
    $items = [];

    for ($position = $from; $position < $to; $position++) {
        $id = searchTrackId($position);
        $items[] = [...$template, 'id' => $id, 'uri' => "spotify:track:{$id}", 'name' => "Track {$position}"];
    }

    return searchPaging($items, $from, $hasNext, 25);
}

/**
 * A 22-character base62 track id for a position.
 */
function searchTrackId(int $position): string
{
    return sprintf('trk%019d', $position);
}

/**
 * The query string a faked request carried, decoded.
 *
 * @return array<string, string>
 */
function searchSentQuery(Request $request): array
{
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

    return $query;
}

function isSearchRequest(Request $request): bool
{
    return $request->method() === 'GET' && strtok($request->url(), '?') === SEARCH_API_URL;
}

/**
 * Request the search endpoint as JSON, as the given user or as a guest.
 *
 * @param  array<string, string>  $params
 */
function searchRequest(?User $user, array $params): TestResponse
{
    $test = test();

    return ($user ? $test->actingAs($user) : $test)->getJson('/search/results?'.http_build_query($params));
}

// --- A search returns songs, albums and artists from Spotify -----------------------------------------------------

test('Searching Spotify', function () {
    Http::fake(['api.spotify.com/v1/search*' => Http::response(searchFixture())]);

    $response = searchRequest(searchOwner(), ['q' => 'Daft Punk']);

    $response->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => isSearchRequest($request)
        && searchSentQuery($request) == [
            'q' => 'Daft Punk',
            'type' => 'track,album,artist',
            'limit' => '10',
            'offset' => '0',
            'market' => 'from_token',
        ]
        && $request->hasHeader('Authorization', 'Bearer BQ-access-123'));

    expect(array_keys($response->json()))->toBe(['songs', 'albums', 'artists'])
        ->and(array_column($response->json('songs.items'), 'id'))
        ->toBe(['0DiWol3AO6WpXZgp0goxAV', '5W3cjX2J3tjhG8zb6u0qHn', '1pKYYY0dkg23sQQXi0Q5zN'])
        ->and(array_column($response->json('albums.items'), 'id'))->toBe(['2noRn2Aes5aoNVsU6iWThc', '5uRdvUR7xCnHmUW8n64n9y'])
        ->and(array_column($response->json('artists.items'), 'id'))->toBe(['4tZwfgrHOc3mvqYlEYSvVi']);
});

test('Surrounding spaces do not count', function () {
    Http::fake(['api.spotify.com/v1/search*' => Http::response(searchFixture())]);

    searchRequest(searchOwner(), ['q' => '   Daft Punk   '])->assertOk();

    Http::assertSent(fn (Request $request) => searchSentQuery($request)['q'] === 'Daft Punk');
});

test('Special characters reach Spotify unchanged', function () {
    Http::fake(['api.spotify.com/v1/search*' => Http::response(searchFixture())]);

    searchRequest(searchOwner(), ['q' => 'AC/DC & "Back in Black" #1 Beyoncé'])->assertOk();

    Http::assertSent(fn (Request $request) => searchSentQuery($request)['q'] === 'AC/DC & "Back in Black" #1 Beyoncé');
});

test('No results', function () {
    Http::fake(['api.spotify.com/v1/search*' => Http::response([
        'tracks' => searchPaging([]),
        'albums' => searchPaging([]),
        'artists' => searchPaging([]),
    ])]);

    searchRequest(searchOwner(), ['q' => 'zzqxjkwvvy'])
        ->assertOk()
        ->assertExactJson([
            'songs' => ['items' => [], 'next_cursor' => null],
            'albums' => ['items' => [], 'next_cursor' => null],
            'artists' => ['items' => [], 'next_cursor' => null],
        ]);
});

// --- Results use the app's common format -------------------------------------------------------------------------

test('Songs have the common format', function () {
    Http::fake(['api.spotify.com/v1/search*' => Http::response(searchFixture())]);

    $songs = searchRequest(searchOwner(), ['q' => 'Daft Punk'])->json('songs.items');

    expect($songs)->not->toBeEmpty();

    foreach ($songs as $song) {
        expect(array_keys($song))
            ->toBe(['source', 'id', 'uri', 'title', 'artists', 'album', 'duration_ms', 'image', 'playable'])
            ->and($song['source'])->toBe('spotify')
            ->and($song['artists'])->toBe(['Daft Punk'])
            ->and($song['album'])->toBeIn(['Discovery', 'Homework'])
            ->and($song['image'] === null || str_starts_with($song['image'], 'https://'))->toBeTrue();

        foreach (['name', 'images', 'href', 'external_urls', 'is_playable'] as $spotifyKey) {
            expect($song)->not->toHaveKey($spotifyKey);
        }
    }
});

test('Albums have the common format', function () {
    Http::fake(['api.spotify.com/v1/search*' => Http::response(searchFixture())]);

    $albums = searchRequest(searchOwner(), ['q' => 'Daft Punk'])->json('albums.items');

    expect($albums)->not->toBeEmpty();

    foreach ($albums as $album) {
        expect(array_keys($album))->toBe(['source', 'id', 'uri', 'title', 'artists', 'year', 'image'])
            ->and($album['source'])->toBe('spotify');

        foreach (['name', 'images', 'href', 'release_date', 'total_tracks'] as $spotifyKey) {
            expect($album)->not->toHaveKey($spotifyKey);
        }
    }
});

test('Artists have the common format', function () {
    Http::fake(['api.spotify.com/v1/search*' => Http::response(searchFixture())]);

    $artists = searchRequest(searchOwner(), ['q' => 'Daft Punk'])->json('artists.items');

    expect($artists)->not->toBeEmpty();

    foreach ($artists as $artist) {
        expect(array_keys($artist))->toBe(['source', 'id', 'uri', 'title', 'image'])
            ->and($artist['source'])->toBe('spotify');

        foreach (['name', 'images', 'href', 'genres', 'followers'] as $spotifyKey) {
            expect($artist)->not->toHaveKey($spotifyKey);
        }
    }
});

// --- A query must be 1 to 200 characters -------------------------------------------------------------------------

test('An invalid query', function (array $params) {
    Http::fake();

    searchRequest(searchOwner(), $params)->assertUnprocessable();

    Http::assertNothingSent();
})->with([
    'no q' => [[]],
    'q "   "' => [['q' => '   ']],
    'q of 201 characters' => [['q' => str_repeat('a', 201)]],
]);

test('a query of exactly 200 characters is searched', function () {
    Http::fake(['api.spotify.com/v1/search*' => Http::response(searchFixture())]);

    searchRequest(searchOwner(), ['q' => str_repeat('é', 200)])->assertOk();

    Http::assertSentCount(1);
});

// --- Sections page with an opaque cursor -------------------------------------------------------------------------

test('Paging through songs', function () {
    $fixture = searchFixture();

    Http::fake(function (Request $request) use ($fixture) {
        $query = searchSentQuery($request);

        return match ([$query['type'], $query['offset']]) {
            ['track,album,artist', '0'] => Http::response([...$fixture, 'tracks' => searchTracksPage(0, 10, true)]),
            ['track', '10'] => Http::response(['tracks' => searchTracksPage(10, 20, true)]),
            ['track', '20'] => Http::response(['tracks' => searchTracksPage(20, 25, false)]),
        };
    });

    $owner = searchOwner();

    $first = searchRequest($owner, ['q' => 'Daft Punk'])->assertOk();
    $cursor = $first->json('songs.next_cursor');

    expect($cursor)->toBeString()->not->toBeEmpty()
        ->and(array_keys($first->json('songs')))->toBe(['items', 'next_cursor'])
        ->and($first->json('songs'))->not->toHaveKey('offset')
        ->and($first->json('songs'))->not->toHaveKey('next');

    $second = searchRequest($owner, ['q' => 'Daft Punk', 'section' => 'songs', 'cursor' => $cursor])->assertOk();

    Http::assertSent(fn (Request $request) => isSearchRequest($request)
        && searchSentQuery($request) == [
            'q' => 'Daft Punk',
            'type' => 'track',
            'limit' => '10',
            'offset' => '10',
            'market' => 'from_token',
        ]);

    expect(array_keys($second->json()))->toBe(['items', 'next_cursor'])
        ->and(array_column($second->json('items'), 'id'))->toBe(array_map(searchTrackId(...), range(10, 19)))
        ->and($second->json('next_cursor'))->toBeString()->not->toBeEmpty();

    $third = searchRequest($owner, ['q' => 'Daft Punk', 'section' => 'songs', 'cursor' => $second->json('next_cursor')])
        ->assertOk();

    expect(array_column($third->json('items'), 'id'))->toBe(array_map(searchTrackId(...), range(20, 24)))
        ->and($third->json('next_cursor'))->toBeNull();

    Http::assertSentCount(3);
});

test('No more results', function () {
    Http::fake(['api.spotify.com/v1/search*' => Http::response(searchFixture())]);

    $response = searchRequest(searchOwner(), ['q' => 'Daft Punk'])->assertOk();

    expect($response->json('albums.items'))->toHaveCount(2)
        ->and($response->json('albums.next_cursor'))->toBeNull();
});

test('no cursor is offered once the next offset would reach 1000', function () {
    Http::fake(['api.spotify.com/v1/search*' => Http::response(['tracks' => searchTracksPage(0, 10, true)])]);

    $cursor = SearchCursor::encode(SearchSection::Songs, 'Daft Punk', 990);

    searchRequest(searchOwner(), ['q' => 'Daft Punk', 'section' => 'songs', 'cursor' => $cursor])
        ->assertOk()
        ->assertJsonPath('next_cursor', null);

    Http::assertSent(fn (Request $request) => searchSentQuery($request)['offset'] === '990');
});

// --- An invalid cursor is rejected -------------------------------------------------------------------------------

test('An invalid section or cursor', function (Closure $input) {
    Http::fake();

    searchRequest(searchOwner(), ['q' => 'Daft Punk', ...$input()])->assertUnprocessable();

    Http::assertNothingSent();
})->with([
    'section "playlists"' => [fn () => ['section' => 'playlists']],
    'a valid songs cursor and no section' => [fn () => [
        'cursor' => SearchCursor::encode(SearchSection::Songs, 'Daft Punk', 10),
    ]],
    'section "songs" and cursor "not-a-cursor"' => [fn () => ['section' => 'songs', 'cursor' => 'not-a-cursor']],
    'section "songs" and an altered songs cursor' => [function () {
        $cursor = SearchCursor::encode(SearchSection::Songs, 'Daft Punk', 10);
        $middle = intdiv(strlen($cursor), 2);

        return ['section' => 'songs', 'cursor' => substr_replace($cursor, $cursor[$middle] === 'A' ? 'B' : 'A', $middle, 1)];
    }],
    'section "songs" and a cursor with offset 1010' => [fn () => [
        'section' => 'songs',
        'cursor' => SearchCursor::encode(SearchSection::Songs, 'Daft Punk', 1010),
    ]],
    'section "albums" and a valid songs cursor' => [fn () => [
        'section' => 'albums',
        'cursor' => SearchCursor::encode(SearchSection::Songs, 'Daft Punk', 10),
    ]],
    'section "songs" and a songs cursor made for "Justice"' => [fn () => [
        'section' => 'songs',
        'cursor' => SearchCursor::encode(SearchSection::Songs, 'Justice', 10),
    ]],
    'section "songs" and no cursor' => [fn () => ['section' => 'songs']],
]);

// --- Search failures answer in JSON ------------------------------------------------------------------------------

test('The search cannot be answered', function (Closure $situation, int $status, array $json) {
    $user = $situation();

    $response = searchRequest($user, ['q' => 'Daft Punk'])
        ->assertStatus($status)
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson($json);

    expect($response->getContent())->not->toContain('<html')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
})->with([
    'a visitor without a session' => [fn () => null, 401, ['error' => 'unauthenticated']],
    'Spotify not connected' => [fn () => User::factory()->create(), 409, ['error' => 'spotify_not_connected']],
    'the refresh rejected with invalid_grant' => [function () {
        Http::fake([SpotifyOAuth::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400)]);

        return searchOwner(-10);
    }, 409, ['error' => 'spotify_reconnect_required']],
    'Spotify answering 429 with Retry-After: 30' => [function () {
        Http::fake(['api.spotify.com/v1/search*' => Http::response([], 429, ['Retry-After' => '30'])]);

        return searchOwner();
    }, 429, ['error' => 'rate_limited', 'retry_after' => 30]],
    'Spotify answering 503' => [function () {
        Http::fake(['api.spotify.com/v1/search*' => Http::response([], 503)]);

        return searchOwner();
    }, 502, ['error' => 'spotify_unavailable']],
    'Spotify not answering before the timeout' => [function () {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        return searchOwner();
    }, 502, ['error' => 'spotify_unavailable']],
    'Spotify answering 403 for an account outside the app\'s users' => [function () {
        Http::fake(['api.spotify.com/v1/search*' => Http::response(
            ['error' => ['status' => 403, 'message' => 'Check settings on developer.spotify.com/dashboard, the user may not be registered.']],
            403,
        )]);

        return searchOwner();
    }, 502, ['error' => 'spotify_unavailable']],
]);

test('a guest plain request is still sent to the login page', function () {
    $this->get('/search/results?q=Daft+Punk')->assertRedirect('/login');
});

test('The rate-limit pause covers the search', function () {
    Http::fake(['api.spotify.com/v1/search*' => Http::sequence()
        ->push([], 429, ['Retry-After' => '30'])
        ->push(searchFixture()),
    ]);

    $owner = searchOwner();

    searchRequest($owner, ['q' => 'Daft Punk'])
        ->assertStatus(429)
        ->assertExactJson(['error' => 'rate_limited', 'retry_after' => 30]);

    $this->travel(10)->seconds();

    searchRequest($owner, ['q' => 'Justice'])
        ->assertStatus(429)
        ->assertExactJson(['error' => 'rate_limited', 'retry_after' => 20]);

    Http::assertSentCount(1);

    $this->travel(21)->seconds();

    searchRequest($owner, ['q' => 'Justice'])->assertOk();

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => isSearchRequest($request) && searchSentQuery($request)['q'] === 'Justice');
});

test('An expired token does not stop the search', function () {
    Http::fake([
        SpotifyOAuth::TOKEN_URL => Http::response(['access_token' => 'BQ-fresh-789', 'expires_in' => 3600]),
        'api.spotify.com/v1/search*' => Http::response(searchFixture()),
    ]);

    searchRequest(searchOwner(-60), ['q' => 'Daft Punk'])
        ->assertOk()
        ->assertJsonCount(3, 'songs.items');

    Http::assertSent(fn (Request $request) => isSearchRequest($request)
        && $request->hasHeader('Authorization', 'Bearer BQ-fresh-789'));
});
