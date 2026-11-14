<?php

// spotify-library-overview, tasks 2.1 and 2.2: "GET /library" and "POST /library/refresh" on the props, the requests
// sent to Spotify, the cache and every state (spec "music-library"). Spotify is faked; no test reaches it. Helper
// names carry a "libraryFeature" prefix because Pest declares them globally.

use App\Models\SpotifyConnection;
use App\Models\User;
use App\Spotify\SpotifyClient;
use App\Spotify\SpotifyOAuth;
use App\Spotify\SpotifyScopes;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
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
 * The fixture's playlists for the owner "owner-123": "Road Trip" (own), "Indie Picks" (followed) and "Office Jams"
 * (collaborative of another user).
 *
 * @return list<array<string, mixed>>
 */
function libraryFeatureFixture(): array
{
    return json_decode(file_get_contents(base_path('tests/Fixtures/spotify/me-playlists.json')), true)['items'];
}

/**
 * An own playlist with the given name and position, built over the fixture's first playlist.
 *
 * @return array<string, mixed>
 */
function libraryFeaturePlaylist(string $name, int $position): array
{
    $id = sprintf('pl%020d', $position);

    return [...libraryFeatureFixture()[0], 'id' => $id, 'uri' => "spotify:playlist:{$id}", 'name' => $name];
}

/**
 * The owner with a Spotify connection; $attributes override the factory's.
 */
function libraryFeatureOwner(array $attributes = []): User
{
    return SpotifyConnection::factory()->create(['access_token_expires_at' => now()->addHour(), ...$attributes])->user;
}

/**
 * Fake Spotify's library: the playlists, served in pages by limit and offset, and the saved-song total. The arrays
 * are read on every request, so a test can change them between requests.
 *
 * @param  list<array<string, mixed>>  $playlists
 */
function libraryFeatureFake(array &$playlists, int &$likedTotal): void
{
    Http::fake([
        'api.spotify.com/v1/me/playlists*' => function (Request $request) use (&$playlists) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $offset = (int) $query['offset'];
            $limit = (int) $query['limit'];
            $more = $offset + $limit < count($playlists);

            return Http::response([
                'href' => "https://api.spotify.com/v1/me/playlists?offset={$offset}&limit={$limit}",
                'limit' => $limit,
                'next' => $more ? 'https://api.spotify.com/v1/me/playlists?offset='.($offset + $limit)."&limit={$limit}" : null,
                'offset' => $offset,
                'previous' => null,
                'total' => count($playlists),
                'items' => array_slice($playlists, $offset, $limit),
            ]);
        },
        'api.spotify.com/v1/me/tracks*' => function () use (&$likedTotal) {
            return Http::response(['href' => 'https://api.spotify.com/v1/me/tracks?offset=0&limit=1', 'items' => [], 'limit' => 1, 'next' => null, 'offset' => 0, 'previous' => null, 'total' => $likedTotal]);
        },
    ]);
}

/**
 * The decoded query strings of the requests sent to one Web API path.
 *
 * @return list<array<string, string>>
 */
function libraryFeatureSent(string $path): array
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

/**
 * The page props of an Inertia response.
 *
 * @return array<string, mixed>
 */
function libraryFeatureProps(TestResponse $response): array
{
    return $response->viewData('page')['props'];
}

// --- The owner has a library page (task 2.1) ---------------------------------------------------------------------

test('A visitor without a session', function () {
    Http::fake();

    $this->get('/library')->assertRedirect('/login');
    $this->post('/library/refresh')->assertRedirect('/login');

    Http::assertNothingSent();
});

// --- The library shows every playlist and the Liked Songs count --------------------------------------------------

test('Seeing the library', function () {
    $playlists = [libraryFeaturePlaylist('Road Trip', 0), libraryFeaturePlaylist('Chill', 1), libraryFeaturePlaylist('Gym', 2)];
    $liked = 12;
    libraryFeatureFake($playlists, $liked);

    $this->actingAs(libraryFeatureOwner())
        ->get('/library')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library')
            ->has('playlists', 3)
            ->where('playlists.0.title', 'Road Trip')
            ->where('playlists.1.title', 'Chill')
            ->where('playlists.2.title', 'Gym')
            ->where('playlists.0.image', 'https://mosaic.scdn.co/640/ab67616d0000b2733b5e11ca1b063583df9492db')
            ->where('playlistsAllowed', true)
            ->where('likedTotal', 12)
            ->where('likedAllowed', true)
            ->where('error', null));
});

test('Many playlists', function () {
    $playlists = array_map(fn (int $i) => libraryFeaturePlaylist("List {$i}", $i), range(0, 119));
    $liked = 12;
    libraryFeatureFake($playlists, $liked);

    $this->actingAs(libraryFeatureOwner())
        ->get('/library')
        ->assertInertia(fn (Assert $page) => $page->has('playlists', 120)->where('playlists.119.title', 'List 119'));

    expect(libraryFeatureSent('/me/playlists'))->toBe([
        ['limit' => '50', 'offset' => '0'],
        ['limit' => '50', 'offset' => '50'],
        ['limit' => '50', 'offset' => '100'],
    ])->and(libraryFeatureSent('/me/tracks'))->toBe([['limit' => '1']]);
});

test('reading the playlists stops after 20 requests, even when Spotify still gives a next page', function () {
    $item = libraryFeaturePlaylist('Endless', 0);

    Http::fake([
        'api.spotify.com/v1/me/playlists*' => Http::response([
            'items' => array_fill(0, 50, $item),
            'next' => 'https://api.spotify.com/v1/me/playlists?offset=999&limit=50',
        ]),
        'api.spotify.com/v1/me/tracks*' => Http::response(['total' => 0]),
    ]);

    $this->actingAs(libraryFeatureOwner())
        ->get('/library')
        ->assertInertia(fn (Assert $page) => $page->has('playlists', 1000));

    $sent = libraryFeatureSent('/me/playlists');

    expect($sent)->toHaveCount(20)
        ->and($sent[19]['offset'])->toBe('950');
});

// --- Playlists use the app's common format -----------------------------------------------------------------------

test('Playlist props have the common format', function () {
    $playlists = array_slice(libraryFeatureFixture(), 0, 2);
    $liked = 3;
    libraryFeatureFake($playlists, $liked);

    $props = libraryFeatureProps($this->actingAs(libraryFeatureOwner())->get('/library'));

    expect($props['playlists'])->toHaveCount(2);

    foreach ($props['playlists'] as $playlist) {
        expect(array_keys($playlist))
            ->toBe(['source', 'id', 'uri', 'title', 'image', 'owner', 'collaborative', 'can_list_tracks', 'total'])
            ->and($playlist['source'])->toBe('spotify');
    }
});

test('A playlist without a cover', function () {
    $playlists = [[...libraryFeaturePlaylist('Untitled Mix', 0), 'images' => []]];
    $liked = 0;
    libraryFeatureFake($playlists, $liked);

    $this->actingAs(libraryFeatureOwner())
        ->get('/library')
        ->assertInertia(fn (Assert $page) => $page->where('playlists.0.title', 'Untitled Mix')->where('playlists.0.image', null));
});

test('A playlist the owner only follows', function () {
    $playlists = libraryFeatureFixture();
    $liked = 3;
    libraryFeatureFake($playlists, $liked);

    $this->actingAs(libraryFeatureOwner())
        ->get('/library')
        ->assertInertia(fn (Assert $page) => $page
            ->where('playlists.1.title', 'Indie Picks')
            ->where('playlists.1.owner', 'Alice')
            ->where('playlists.1.can_list_tracks', false)
            ->where('playlists.1.total', null));
});

test('A collaborative playlist', function () {
    $playlists = libraryFeatureFixture();
    $liked = 3;
    libraryFeatureFake($playlists, $liked);

    $this->actingAs(libraryFeatureOwner())
        ->get('/library')
        ->assertInertia(fn (Assert $page) => $page
            ->where('playlists.2.title', 'Office Jams')
            ->where('playlists.2.collaborative', true)
            ->where('playlists.2.can_list_tracks', true));
});

// --- The library is cached for 10 minutes (task 2.2) -------------------------------------------------------------

test('The library is served from the cache', function () {
    $playlists = libraryFeatureFixture();
    $liked = 3;
    libraryFeatureFake($playlists, $liked);
    $owner = libraryFeatureOwner();

    $this->actingAs($owner)->get('/library')->assertOk();
    $this->travel(9)->minutes();
    $this->actingAs($owner)->get('/library')
        ->assertInertia(fn (Assert $page) => $page->has('playlists', 3)->where('likedTotal', 3));

    expect(libraryFeatureSent('/me/playlists'))->toHaveCount(1)
        ->and(libraryFeatureSent('/me/tracks'))->toHaveCount(1);
});

test('The cache expires after 10 minutes', function () {
    $playlists = libraryFeatureFixture();
    $liked = 3;
    libraryFeatureFake($playlists, $liked);
    $owner = libraryFeatureOwner();

    $this->actingAs($owner)->get('/library')->assertOk();
    $this->travel(11)->minutes();
    $this->actingAs($owner)->get('/library')->assertOk();

    expect(libraryFeatureSent('/me/playlists'))->toHaveCount(2);
});

test('Refreshing the library', function () {
    $playlists = libraryFeatureFixture();
    $liked = 3;
    libraryFeatureFake($playlists, $liked);
    $owner = libraryFeatureOwner();

    $this->actingAs($owner)->get('/library')->assertOk();

    $playlists[] = libraryFeaturePlaylist('Fresh', 3);

    $this->actingAs($owner)->post('/library/refresh')->assertRedirect('/library');
    $this->actingAs($owner)->get('/library')
        ->assertInertia(fn (Assert $page) => $page->has('playlists', 4)->where('playlists.3.title', 'Fresh'));

    expect(libraryFeatureSent('/me/playlists'))->toHaveCount(2);
});

test('A new connection does not read the old cache', function () {
    $playlists = libraryFeatureFixture();
    $liked = 3;
    libraryFeatureFake($playlists, $liked);
    $owner = libraryFeatureOwner();

    $this->actingAs($owner)->get('/library')->assertOk();

    // Disconnect and connect the same account again: a new connection identifier.
    SpotifyConnection::query()->delete();
    SpotifyConnection::factory()->for($owner)->create(['access_token_expires_at' => now()->addHour()]);

    $this->actingAs($owner)->get('/library')->assertOk();

    expect(libraryFeatureSent('/me/playlists'))->toHaveCount(2);
});

// --- The library asks for a Spotify connection when it lacks one -------------------------------------------------

test('Spotify not connected', function () {
    Http::fake();

    $this->actingAs(User::factory()->create())
        ->get('/library')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library')
            ->where('error', 'spotify_not_connected')
            ->where('playlists', null)
            ->where('likedTotal', null));

    Http::assertNothingSent();
});

test('Disconnecting hides the cached library', function () {
    $playlists = libraryFeatureFixture();
    $liked = 3;
    libraryFeatureFake($playlists, $liked);
    $owner = libraryFeatureOwner();

    $this->actingAs($owner)->get('/library')->assertOk();

    SpotifyConnection::query()->delete();

    $this->actingAs($owner)
        ->get('/library')
        ->assertInertia(fn (Assert $page) => $page->where('error', 'spotify_not_connected')->where('playlists', null));
});

test('Spotify access was revoked', function () {
    Http::fake([SpotifyOAuth::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400)]);

    $this->actingAs(libraryFeatureOwner(['access_token_expires_at' => now()->subMinute()]))
        ->get('/library')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('error', 'spotify_reconnect_required')
            ->where('playlists', null)
            ->where('likedTotal', null));

    expect(libraryFeatureSent('/me/playlists'))->toBe([]);
});

// --- The library asks for a missing permission -------------------------------------------------------------------

test('The saved-songs permission is missing', function () {
    $playlists = libraryFeatureFixture();
    $liked = 3;
    libraryFeatureFake($playlists, $liked);
    $scopes = implode(' ', array_diff(SpotifyScopes::REQUIRED, ['user-library-read']));

    $this->actingAs(libraryFeatureOwner(['scopes' => $scopes]))
        ->get('/library')
        ->assertInertia(fn (Assert $page) => $page
            ->has('playlists', 3)
            ->where('likedAllowed', false)
            ->where('likedTotal', null));

    expect(libraryFeatureSent('/me/tracks'))->toBe([]);
});

test('The playlists permission is missing', function () {
    $playlists = libraryFeatureFixture();
    $liked = 3;
    libraryFeatureFake($playlists, $liked);
    $scopes = implode(' ', array_diff(SpotifyScopes::REQUIRED, ['playlist-read-private']));

    $this->actingAs(libraryFeatureOwner(['scopes' => $scopes]))
        ->get('/library')
        ->assertInertia(fn (Assert $page) => $page
            ->where('playlistsAllowed', false)
            ->where('playlists', null)
            ->where('likedTotal', 3));

    expect(libraryFeatureSent('/me/playlists'))->toBe([]);
});

// --- Empty libraries say so ----------------------------------------------------------------------------------------

test('An empty library', function () {
    $playlists = [];
    $liked = 0;
    libraryFeatureFake($playlists, $liked);

    $this->actingAs(libraryFeatureOwner())
        ->get('/library')
        ->assertInertia(fn (Assert $page) => $page
            ->where('playlists', [])
            ->where('likedTotal', 0)
            ->where('playlistsAllowed', true)
            ->where('likedAllowed', true));
});

test('Saved songs but no playlists', function () {
    $playlists = [];
    $liked = 12;
    libraryFeatureFake($playlists, $liked);

    $this->actingAs(libraryFeatureOwner())
        ->get('/library')
        ->assertInertia(fn (Assert $page) => $page->where('playlists', [])->where('likedTotal', 12));
});

test('A missing permission is not an empty library', function () {
    $playlists = [];
    $liked = 0;
    libraryFeatureFake($playlists, $liked);
    $scopes = implode(' ', array_diff(SpotifyScopes::REQUIRED, ['playlist-read-private']));

    $this->actingAs(libraryFeatureOwner(['scopes' => $scopes]))
        ->get('/library')
        ->assertInertia(fn (Assert $page) => $page
            ->where('playlistsAllowed', false)
            ->where('playlists', null)
            ->where('likedTotal', 0));
});

// --- Library failures show a message instead of an error page ----------------------------------------------------

test('Spotify fails', function () {
    Http::fake([
        'api.spotify.com/v1/me/playlists*' => Http::response(['error' => ['status' => 503, 'message' => 'upstream-body-detail']], 503),
        'api.spotify.com/v1/me/tracks*' => Http::response(['total' => 3]),
    ]);

    $response = $this->actingAs(libraryFeatureOwner())->get('/library');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Library')->where('error', 'spotify_unavailable')->where('playlists', null))
        ->assertDontSee('upstream-body-detail')
        ->assertDontSee('BQ-access-123');

    expect(Cache::get('library:spotify:'.SpotifyConnection::sole()->connection_id))->toBeNull();
});

test('a refusal such as a 403 on the playlists is a failure too', function () {
    Http::fake(['api.spotify.com/v1/me/playlists*' => Http::response(['error' => ['status' => 403, 'message' => 'Forbidden']], 403)]);

    $this->actingAs(libraryFeatureOwner())
        ->get('/library')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('error', 'spotify_unavailable'));
});

test('Spotify is busy and nothing is kept', function () {
    Http::fake();
    Cache::put(SpotifyClient::PAUSE_KEY, now()->getTimestamp() + 30, 30);

    $this->actingAs(libraryFeatureOwner())
        ->get('/library')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('error', 'rate_limited')->where('playlists', null));

    Http::assertNothingSent();
});

test('a kept library is still served during the rate-limit pause', function () {
    $playlists = libraryFeatureFixture();
    $liked = 3;
    libraryFeatureFake($playlists, $liked);
    $owner = libraryFeatureOwner();

    $this->actingAs($owner)->get('/library')->assertOk();
    Cache::put(SpotifyClient::PAUSE_KEY, now()->getTimestamp() + 30, 30);

    $this->actingAs($owner)
        ->get('/library')
        ->assertInertia(fn (Assert $page) => $page->where('error', null)->has('playlists', 3));
});
