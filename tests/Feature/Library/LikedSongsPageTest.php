<?php

// spotify-library-tracks, task 3.2: the Liked Songs page and its "Load more" endpoint on the props, the requests sent
// to Spotify, the cursors and the states (spec "music-library"). Spotify is faked; no test reaches it. Helper names
// carry a "likedFeature" prefix because Pest declares them globally.

use App\Http\Paging\LibraryCursor;
use App\Models\SpotifyConnection;
use App\Models\User;
use App\Spotify\SpotifyClient;
use App\Spotify\SpotifyScopes;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

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
 * The fixture's saved songs: Song X, Song Y and Song Z, newest first.
 *
 * @return list<array<string, mixed>>
 */
function likedFeatureEntries(): array
{
    return json_decode(file_get_contents(base_path('tests/Fixtures/spotify/me-tracks.json')), true)['items'];
}

/**
 * Fake the saved songs: $entries, or $total generated ones, served 50 per page.
 *
 * @param  list<array<string, mixed>>|null  $entries
 */
function likedFeatureFake(?array $entries = null, ?int $total = null): void
{
    $template = likedFeatureEntries()[0];

    Http::fake(['api.spotify.com/v1/me/tracks*' => function (Request $request) use ($entries, $total, $template) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $offset = (int) $query['offset'];
        $count = $total ?? count($entries ?? []);
        $page = $entries !== null
            ? array_slice($entries, $offset, 50)
            : array_map(function (int $i) use ($template) {
                $id = sprintf('lk%020d', $i);

                return [...$template, 'track' => [...$template['track'], 'id' => $id, 'uri' => "spotify:track:{$id}", 'name' => "Saved {$i}"]];
            }, range($offset, min($offset + 50, $count) - 1));

        return Http::response([
            'items' => $page,
            'limit' => 50,
            'next' => $offset + 50 < $count ? 'https://api.spotify.com/v1/me/tracks?offset='.($offset + 50) : null,
            'offset' => $offset,
            'total' => $count,
        ]);
    }]);
}

function likedFeatureOwner(array $attributes = []): User
{
    return SpotifyConnection::factory()->create(['access_token_expires_at' => now()->addHour(), ...$attributes])->user;
}

/**
 * @return list<array<string, string>>
 */
function likedFeatureSent(): array
{
    return Http::recorded()
        ->filter(fn (array $pair) => strtok($pair[0]->url(), '?') === 'https://api.spotify.com/v1/me/tracks')
        ->map(function (array $pair) {
            parse_str((string) parse_url($pair[0]->url(), PHP_URL_QUERY), $query);

            return $query;
        })
        ->values()
        ->all();
}

// --- The Liked Songs page lists saved songs 50 at a time ---------------------------------------------------------

test('Seeing the saved songs', function () {
    likedFeatureFake(likedFeatureEntries());

    $response = $this->actingAs(likedFeatureOwner())->get('/library/liked');

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('library/Liked')
        ->where('allowed', true)
        ->where('total', 3)
        ->has('songs', 3)
        ->where('songs.0.title', 'Song X')
        ->where('songs.0.added_at', '2026-11-10T09:00:00Z')
        ->where('songs.2.title', 'Song Z')
        ->where('nextCursor', null)
        ->where('error', null));

    foreach ($response->viewData('page')['props']['songs'] as $song) {
        expect(array_keys($song))
            ->toBe(['source', 'id', 'uri', 'title', 'artists', 'album', 'duration_ms', 'image', 'playable', 'added_at']);
    }

    expect(likedFeatureSent())->toBe([['limit' => '50', 'offset' => '0', 'market' => 'from_token']]);
});

test('Thousands of saved songs', function () {
    likedFeatureFake(total: 3200);
    $owner = likedFeatureOwner();

    $first = $this->actingAs($owner)->get('/library/liked');
    $first->assertInertia(fn (Assert $page) => $page->has('songs', 50)->where('total', 3200));

    expect(likedFeatureSent())->toBe([['limit' => '50', 'offset' => '0', 'market' => 'from_token']]);

    $cursor = $first->viewData('page')['props']['nextCursor'];

    $this->actingAs($owner)
        ->getJson('/library/liked/songs?'.http_build_query(['cursor' => $cursor]))
        ->assertOk()
        ->assertJsonCount(50, 'items')
        ->assertJsonPath('items.0.title', 'Saved 50');

    expect(array_column(likedFeatureSent(), 'offset'))->toBe(['0', '50']);
});

// --- Liked Songs says when it is empty or lacks permission -------------------------------------------------------

test('No saved songs', function () {
    likedFeatureFake([]);

    $this->actingAs(likedFeatureOwner())
        ->get('/library/liked')
        ->assertInertia(fn (Assert $page) => $page->where('songs', [])->where('total', 0)->where('allowed', true));
});

test('The saved-songs permission is missing on Liked Songs', function () {
    Http::fake();
    $scopes = implode(' ', array_diff(SpotifyScopes::REQUIRED, ['user-library-read']));
    $owner = likedFeatureOwner(['scopes' => $scopes]);

    $this->actingAs($owner)
        ->get('/library/liked')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('allowed', false)->where('songs', null)->where('error', null));

    $this->actingAs($owner)
        ->getJson('/library/liked/songs?'.http_build_query(['cursor' => LibraryCursor::encode(LibraryCursor::LIKED_SCOPE, 50)]))
        ->assertForbidden()
        ->assertExactJson(['error' => 'permission_missing']);

    Http::assertNothingSent();
});

// --- "Load more" adds the next songs -------------------------------------------------------------------------------

test('A foreign cursor is rejected', function (Closure $cursor) {
    Http::fake();

    $this->actingAs(likedFeatureOwner())
        ->getJson('/library/liked/songs?'.http_build_query(['cursor' => $cursor()]))
        ->assertUnprocessable();

    Http::assertNothingSent();
})->with([
    'a cursor made for a playlist' => [fn () => LibraryCursor::encode(LibraryCursor::playlistScope('3cEYpjA9oz9GiPac4AsH4n'), 50)],
    '"not-a-cursor"' => [fn () => 'not-a-cursor'],
]);

test('a "Load more" without a cursor is rejected', function () {
    Http::fake();

    $this->actingAs(likedFeatureOwner())->getJson('/library/liked/songs')->assertUnprocessable();
    $this->actingAs(likedFeatureOwner())->getJson('/library/playlists/spotify/3cEYpjA9oz9GiPac4AsH4n/songs')->assertUnprocessable();

    Http::assertNothingSent();
});

// --- The playlist and Liked Songs pages show connection and failure states ---------------------------------------

test('A page cannot be loaded: Liked Songs', function (Closure $situation, string $error) {
    $owner = $situation();

    $this->actingAs($owner)
        ->get('/library/liked')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('library/Liked')->where('error', $error)->where('songs', null));
})->with([
    'Spotify not connected' => [function () {
        Http::fake();

        return User::factory()->create();
    }, 'spotify_not_connected'],
    'Spotify\'s rate-limit pause in force' => [function () {
        Http::fake();
        Cache::put(SpotifyClient::PAUSE_KEY, now()->getTimestamp() + 30, 30);

        return likedFeatureOwner();
    }, 'rate_limited'],
]);

test('both "Load more" endpoints answer the JSON 401 to a guest', function () {
    Http::fake();

    $this->getJson('/library/liked/songs?cursor=x')->assertUnauthorized()->assertExactJson(['error' => 'unauthenticated']);
    $this->getJson('/library/playlists/spotify/3cEYpjA9oz9GiPac4AsH4n/songs?cursor=x')
        ->assertUnauthorized()
        ->assertExactJson(['error' => 'unauthenticated']);

    Http::assertNothingSent();
});
