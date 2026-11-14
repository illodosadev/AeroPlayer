<?php

// spotify-library-tracks, task 3.1: the playlist page and its "Load more" endpoint on the props, the requests sent to
// Spotify and the states (spec "music-library"). Spotify is faked; no test reaches it. Helper names carry a
// "playlistFeature" prefix because Pest declares them globally.

use App\Http\Paging\LibraryCursor;
use App\Models\SpotifyConnection;
use App\Models\User;
use App\Spotify\SpotifyOAuth;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

const PLAYLIST_FEATURE_ID = '3cEYpjA9oz9GiPac4AsH4n';

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
 * @return array<string, mixed>
 */
function playlistFeatureFixture(string $name): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/spotify/{$name}.json")), true);
}

/**
 * The fixture's entries: Song A, Song B, Song C, My Demo (local), Gone Song (not playable), a deleted song and an
 * episode.
 *
 * @return list<array<string, mixed>|null>
 */
function playlistFeatureEntries(): array
{
    return playlistFeatureFixture('playlist-items')['items'];
}

/**
 * $count generated song entries starting at position $from, built over the fixture's first entry.
 *
 * @return list<array<string, mixed>>
 */
function playlistFeatureGenerated(int $from, int $count): array
{
    $template = playlistFeatureEntries()[0];

    return array_map(function (int $position) use ($template) {
        $id = sprintf('sg%020d', $position);

        return [...$template, 'item' => [...$template['item'], 'id' => $id, 'uri' => "spotify:track:{$id}", 'name' => "Song {$position}"]];
    }, range($from, $from + $count - 1));
}

/**
 * Fake the playlist "Road Trip" (own unless $playlist overrides it) and its entries, served 50 per page.
 *
 * @param  list<array<string, mixed>|null>  $entries
 * @param  array<string, mixed>  $playlist
 */
function playlistFeatureFake(array $entries, array $playlist = [], ?int $total = null): void
{
    $meta = [...playlistFeatureFixture('playlist'), 'items' => ['total' => $total ?? count($entries)], ...$playlist];

    Http::fake([
        'api.spotify.com/v1/playlists/*/items*' => function (Request $request) use ($entries, $total) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $offset = (int) $query['offset'];
            $count = $total ?? count($entries);

            return Http::response([
                'items' => array_slice($entries, $offset, 50),
                'limit' => 50,
                'next' => $offset + 50 < $count ? 'https://api.spotify.com/v1/playlists/x/items?offset='.($offset + 50) : null,
                'offset' => $offset,
                'total' => $count,
            ]);
        },
        'api.spotify.com/v1/playlists/*' => Http::response($meta),
    ]);
}

function playlistFeatureOwner(array $attributes = []): User
{
    return SpotifyConnection::factory()->create(['access_token_expires_at' => now()->addHour(), ...$attributes])->user;
}

/**
 * The decoded query strings of the requests to a Web API path.
 *
 * @return list<array<string, string>>
 */
function playlistFeatureSent(string $path): array
{
    return Http::recorded()
        ->filter(fn (array $pair) => strtok($pair[0]->url(), '?') === 'https://api.spotify.com/v1'.$path)
        ->map(function (array $pair) {
            parse_str((string) parse_url($pair[0]->url(), PHP_URL_QUERY), $query);

            return $query;
        })
        ->values()
        ->all();
}

const PLAYLIST_FEATURE_URL = '/library/playlists/spotify/'.PLAYLIST_FEATURE_ID;

// --- The owner opens a playlist or Liked Songs from the library --------------------------------------------------

test('A visitor without a session', function () {
    Http::fake();

    $this->get('/library/liked')->assertRedirect('/login');
    $this->get('/library/playlists/spotify/road')->assertRedirect('/login');

    Http::assertNothingSent();
});

test('a playlist id that cannot be Spotify\'s is a plain 404', function () {
    Http::fake();

    $this->actingAs(playlistFeatureOwner())->get('/library/playlists/spotify/not-an-id!')->assertNotFound();

    Http::assertNothingSent();
});

// --- A playlist page lists its songs 50 at a time ----------------------------------------------------------------

test('Seeing the songs of an own playlist', function () {
    playlistFeatureFake(array_slice(playlistFeatureEntries(), 0, 3));

    $response = $this->actingAs(playlistFeatureOwner())->get(PLAYLIST_FEATURE_URL);

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('library/Playlist')
        ->where('playlist.title', 'Road Trip')
        ->where('playlist.total', 3)
        ->where('playlist.can_list_tracks', true)
        ->has('songs', 3)
        ->where('songs.0.title', 'Song A')
        ->where('songs.0.duration_ms', 225000)
        ->where('songs.1.title', 'Song B')
        ->where('songs.2.title', 'Song C')
        ->where('nextCursor', null)
        ->where('error', null));

    foreach ($response->viewData('page')['props']['songs'] as $song) {
        expect(array_keys($song))
            ->toBe(['source', 'id', 'uri', 'title', 'artists', 'album', 'duration_ms', 'image', 'playable', 'position', 'local']);
    }

    expect(playlistFeatureSent('/playlists/'.PLAYLIST_FEATURE_ID))->toBe([
        ['fields' => 'id,uri,name,images,owner(id,display_name),collaborative,items(total)'],
    ])->and(playlistFeatureSent('/playlists/'.PLAYLIST_FEATURE_ID.'/items'))->toBe([
        ['limit' => '50', 'offset' => '0', 'market' => 'from_token'],
    ]);
});

test('A playlist with many songs', function () {
    playlistFeatureFake(playlistFeatureGenerated(0, 230));
    $owner = playlistFeatureOwner();

    $first = $this->actingAs($owner)->get(PLAYLIST_FEATURE_URL);

    $first->assertInertia(fn (Assert $page) => $page->has('songs', 50)->where('playlist.total', 230)->where('songs.49.position', 49));

    $cursor = $first->viewData('page')['props']['nextCursor'];

    expect($cursor)->toBeString()->not->toBeEmpty();

    $next = $this->actingAs($owner)->getJson(PLAYLIST_FEATURE_URL.'/songs?'.http_build_query(['cursor' => $cursor]));

    $next->assertOk()->assertJsonCount(50, 'items')->assertJsonPath('items.0.position', 50)->assertJsonPath('items.0.title', 'Song 50');

    expect($next->json('next_cursor'))->toBeString()->not->toBeEmpty()
        ->and($next->headers->get('Cache-Control'))->toContain('no-store')
        ->and(array_column(playlistFeatureSent('/playlists/'.PLAYLIST_FEATURE_ID.'/items'), 'offset'))->toBe(['0', '50']);
});

test('Opening a playlist directly', function () {
    playlistFeatureFake(array_slice(playlistFeatureEntries(), 0, 3));

    $this->actingAs(playlistFeatureOwner())
        ->get(PLAYLIST_FEATURE_URL)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('playlist.title', 'Road Trip')->has('songs', 3));

    expect(playlistFeatureSent('/me/playlists'))->toBe([]);
});

// --- A playlist the owner only follows is playable but not listed ------------------------------------------------

test('Opening a followed playlist', function () {
    playlistFeatureFake([], [
        'id' => '7dKbC1VnGzXSFqY2cVvRmb',
        'uri' => 'spotify:playlist:7dKbC1VnGzXSFqY2cVvRmb',
        'name' => 'Indie Picks',
        'owner' => ['id' => 'alice-789', 'display_name' => 'Alice'],
        'items' => null,
    ]);

    $this->actingAs(playlistFeatureOwner())
        ->get('/library/playlists/spotify/7dKbC1VnGzXSFqY2cVvRmb')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('playlist.title', 'Indie Picks')
            ->where('playlist.can_list_tracks', false)
            ->where('playlist.total', null)
            ->where('songs', null)
            ->where('error', null));

    expect(playlistFeatureSent('/playlists/7dKbC1VnGzXSFqY2cVvRmb/items'))->toBe([]);
});

// --- Some playlist entries cannot play or are hidden -------------------------------------------------------------

test('A local file in a playlist and A song that is not available', function () {
    playlistFeatureFake(playlistFeatureEntries());

    $this->actingAs(playlistFeatureOwner())
        ->get(PLAYLIST_FEATURE_URL)
        ->assertInertia(fn (Assert $page) => $page
            ->has('songs', 5)
            ->where('songs.3.title', 'My Demo')
            ->where('songs.3.local', true)
            ->where('songs.3.playable', false)
            ->where('songs.4.title', 'Gone Song')
            ->where('songs.4.local', false)
            ->where('songs.4.playable', false));
});

test('A song deleted from Spotify', function () {
    $entries = playlistFeatureEntries();
    playlistFeatureFake([$entries[0], $entries[5], $entries[1]]);

    $this->actingAs(playlistFeatureOwner())
        ->get(PLAYLIST_FEATURE_URL)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('playlist.total', 3)
            ->has('songs', 2)
            ->where('songs.0.position', 0)
            ->where('songs.1.title', 'Song B')
            ->where('songs.1.position', 2)
            ->where('error', null));
});

// --- Empty and unavailable playlists say so ----------------------------------------------------------------------

test('An empty playlist', function () {
    playlistFeatureFake([], ['name' => 'New Ideas']);

    $this->actingAs(playlistFeatureOwner())
        ->get(PLAYLIST_FEATURE_URL)
        ->assertInertia(fn (Assert $page) => $page->where('playlist.title', 'New Ideas')->where('playlist.total', 0)->where('songs', []));
});

test('A playlist that is no longer available', function (int $status) {
    Http::fake(['api.spotify.com/v1/playlists/*' => Http::response(['error' => ['status' => $status, 'message' => 'upstream-body-detail']], $status)]);

    $this->actingAs(playlistFeatureOwner())
        ->get(PLAYLIST_FEATURE_URL)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('error', 'playlist_unavailable')->where('playlist', null))
        ->assertDontSee('upstream-body-detail');
})->with([404, 403]);

test('a 403 on the songs of a playlist the owner just left makes it unavailable too', function () {
    Http::fake([
        'api.spotify.com/v1/playlists/*/items*' => Http::response([], 403),
        'api.spotify.com/v1/playlists/*' => Http::response(playlistFeatureFixture('playlist')),
    ]);

    $owner = playlistFeatureOwner();

    $this->actingAs($owner)->get(PLAYLIST_FEATURE_URL)
        ->assertInertia(fn (Assert $page) => $page->where('error', 'playlist_unavailable'));

    $cursor = LibraryCursor::encode(LibraryCursor::playlistScope(PLAYLIST_FEATURE_ID), 50);

    $this->actingAs($owner)->getJson(PLAYLIST_FEATURE_URL.'/songs?'.http_build_query(['cursor' => $cursor]))
        ->assertNotFound()
        ->assertExactJson(['error' => 'playlist_unavailable']);
});

// --- The playlist and Liked Songs pages show connection and failure states ---------------------------------------

test('A page cannot be loaded: a playlist page', function (Closure $situation, string $error) {
    $owner = $situation();

    $this->actingAs($owner)
        ->get(PLAYLIST_FEATURE_URL)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('library/Playlist')->where('error', $error)->where('songs', null));
})->with([
    'the token refresh rejected with invalid_grant' => [function () {
        Http::fake([SpotifyOAuth::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400)]);

        return playlistFeatureOwner(['access_token_expires_at' => now()->subMinute()]);
    }, 'spotify_reconnect_required'],
    'Spotify answering 503 for the playlist' => [function () {
        Http::fake(['api.spotify.com/v1/playlists/*' => Http::response([], 503)]);

        return playlistFeatureOwner();
    }, 'spotify_unavailable'],
    'Spotify not connected' => [function () {
        Http::fake();

        return User::factory()->create();
    }, 'spotify_not_connected'],
]);

test('the playlist "Load more" answers the player\'s JSON errors', function () {
    Http::fake(['api.spotify.com/v1/playlists/*' => Http::response([], 503)]);
    $cursor = LibraryCursor::encode(LibraryCursor::playlistScope(PLAYLIST_FEATURE_ID), 50);

    $this->actingAs(playlistFeatureOwner())
        ->getJson(PLAYLIST_FEATURE_URL.'/songs?'.http_build_query(['cursor' => $cursor]))
        ->assertStatus(502)
        ->assertExactJson(['error' => 'spotify_unavailable']);
});
