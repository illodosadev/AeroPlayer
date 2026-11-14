<?php

// spotify-library-overview, task 1.1: the playlist mapping of design D2, can_list_tracks, total, null entries and the
// exact keys (spec "Playlists use the app's common format").

use App\Application\Library\Results\PlaylistResult;
use App\Spotify\SpotifyCatalogMapper;
use App\Spotify\SpotifyScopes;

/**
 * Spotify's playlists for the owner "owner-123": "Road Trip" (own), "Indie Picks" (followed, without "items") and
 * "Office Jams" (collaborative, owned by "bob-456", without images).
 *
 * @return list<array<string, mixed>>
 */
function playlistMapperItems(): array
{
    return json_decode(file_get_contents(dirname(__DIR__, 2).'/Fixtures/spotify/me-playlists.json'), true)['items'];
}

test('an own playlist maps every field', function () {
    $playlist = (new SpotifyCatalogMapper)->playlists(playlistMapperItems(), 'owner-123')[0];

    expect($playlist->toArray())->toBe([
        'source' => 'spotify',
        'id' => '3cEYpjA9oz9GiPac4AsH4n',
        'uri' => 'spotify:playlist:3cEYpjA9oz9GiPac4AsH4n',
        'title' => 'Road Trip',
        'image' => 'https://mosaic.scdn.co/640/ab67616d0000b2733b5e11ca1b063583df9492db',
        'owner' => 'Alice Owner',
        'collaborative' => false,
        'can_list_tracks' => true,
        'total' => 3,
    ]);
});

test('a followed playlist cannot list its songs and has no total, even with the deprecated tracks field', function () {
    $playlist = (new SpotifyCatalogMapper)->playlists(playlistMapperItems(), 'owner-123')[1];

    expect($playlist->title)->toBe('Indie Picks')
        ->and($playlist->owner)->toBe('Alice')
        ->and($playlist->canListTracks)->toBeFalse()
        ->and($playlist->total)->toBeNull();
});

test('a collaborative playlist of another user can list its songs', function () {
    $playlist = (new SpotifyCatalogMapper)->playlists(playlistMapperItems(), 'owner-123')[2];

    expect($playlist->title)->toBe('Office Jams')
        ->and($playlist->collaborative)->toBeTrue()
        ->and($playlist->canListTracks)->toBeTrue()
        ->and($playlist->total)->toBe(41)
        ->and($playlist->image)->toBeNull();
});

test('a playlist without an owner name has a null owner', function () {
    $item = playlistMapperItems()[0];
    $item['owner']['display_name'] = null;

    expect((new SpotifyCatalogMapper)->playlists([$item], 'owner-123')[0]->owner)->toBeNull();
});

test('without the owner Spotify id, only collaborative playlists can list their songs', function () {
    $playlists = (new SpotifyCatalogMapper)->playlists(playlistMapperItems(), '');

    expect(array_map(fn (PlaylistResult $playlist) => $playlist->canListTracks, $playlists))->toBe([false, false, true]);
});

test('null entries are skipped', function () {
    $items = playlistMapperItems();

    expect((new SpotifyCatalogMapper)->playlists([null, $items[0], null, $items[2]], 'owner-123'))->toHaveCount(2);
});

test('every playlist has exactly the common keys and survives the cache round trip', function () {
    foreach ((new SpotifyCatalogMapper)->playlists(playlistMapperItems(), 'owner-123') as $playlist) {
        expect(array_keys($playlist->toArray()))
            ->toBe(['source', 'id', 'uri', 'title', 'image', 'owner', 'collaborative', 'can_list_tracks', 'total'])
            ->and(PlaylistResult::fromArray($playlist->toArray()))->toEqual($playlist);
    }
});

test('grants() tells whether a scope was granted', function () {
    expect(SpotifyScopes::grants('streaming  playlist-read-private user-library-read', 'playlist-read-private'))->toBeTrue()
        ->and(SpotifyScopes::grants('streaming playlist-read-private', 'user-library-read'))->toBeFalse()
        ->and(SpotifyScopes::grants('', 'streaming'))->toBeFalse()
        ->and(SpotifyScopes::grants('playlist-read-private-x', 'playlist-read-private'))->toBeFalse();
});
