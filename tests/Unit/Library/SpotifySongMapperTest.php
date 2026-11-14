<?php

// spotify-library-tracks, task 2.1: playlist entries and saved songs (design D2): positions with skipped entries and
// an offset, local files, episodes and null items, added_at, and the exact keys.

use App\Spotify\SpotifyCatalogMapper;

/**
 * A fixture from tests/Fixtures/spotify.
 *
 * @return array<string, mixed>
 */
function songMapperFixture(string $name): array
{
    return json_decode(file_get_contents(dirname(__DIR__, 2)."/Fixtures/spotify/{$name}.json"), true);
}

test('playlist entries keep Spotify positions, counted before hidden entries are skipped', function () {
    // The fixture: Song A, Song B, Song C, My Demo (local), Gone Song (not playable), a deleted song, an episode.
    $songs = (new SpotifyCatalogMapper)->playlistSongs(songMapperFixture('playlist-items')['items'], 0);

    expect(array_map(fn ($song) => [$song->song->title, $song->position], $songs))->toBe([
        ['Song A', 0],
        ['Song B', 1],
        ['Song C', 2],
        ['My Demo', 3],
        ['Gone Song', 4],
    ]);
});

test('positions start at the page offset', function () {
    $entries = songMapperFixture('playlist-items')['items'];

    $songs = (new SpotifyCatalogMapper)->playlistSongs([$entries[5], $entries[1]], 50);

    expect($songs)->toHaveCount(1)
        ->and($songs[0]->song->title)->toBe('Song B')
        ->and($songs[0]->position)->toBe(51);
});

test('a local file is local and cannot play; an unplayable song cannot play', function () {
    $songs = (new SpotifyCatalogMapper)->playlistSongs(songMapperFixture('playlist-items')['items'], 0);

    expect($songs[3]->local)->toBeTrue()
        ->and($songs[3]->song->playable)->toBeFalse()
        ->and($songs[4]->local)->toBeFalse()
        ->and($songs[4]->song->playable)->toBeFalse()
        ->and($songs[0]->local)->toBeFalse()
        ->and($songs[0]->song->playable)->toBeTrue();
});

test('the old "track" key of playlist entries is read too', function () {
    $entry = songMapperFixture('playlist-items')['items'][0];
    $entry['track'] = $entry['item'];
    unset($entry['item']);

    expect((new SpotifyCatalogMapper)->playlistSongs([$entry], 0)[0]->song->title)->toBe('Song A');
});

test('a playlist song has exactly the common keys plus position and local', function () {
    $song = (new SpotifyCatalogMapper)->playlistSongs(songMapperFixture('playlist-items')['items'], 0)[0];

    expect(array_keys($song->toArray()))
        ->toBe(['source', 'id', 'uri', 'title', 'artists', 'album', 'duration_ms', 'image', 'playable', 'position', 'local'])
        ->and($song->toArray())->toMatchArray([
            'source' => 'spotify',
            'id' => '4uLU6hMCjMI75M1A2tKUQC',
            'title' => 'Song A',
            'duration_ms' => 225000,
            'position' => 0,
            'local' => false,
        ]);
});

test('saved songs keep their order and when they were saved', function () {
    $songs = (new SpotifyCatalogMapper)->likedSongs(songMapperFixture('me-tracks')['items']);

    expect(array_map(fn ($song) => [$song->song->title, $song->addedAt], $songs))->toBe([
        ['Song X', '2026-11-10T09:00:00Z'],
        ['Song Y', '2026-11-05T09:00:00Z'],
        ['Song Z', '2026-10-28T09:00:00Z'],
    ]);
});

test('saved entries without a song are skipped', function () {
    $entries = songMapperFixture('me-tracks')['items'];

    expect((new SpotifyCatalogMapper)->likedSongs([null, ['added_at' => '2026-01-01T00:00:00Z', 'track' => null], $entries[0]]))
        ->toHaveCount(1);
});

test('a saved song has exactly the common keys plus added_at', function () {
    $song = (new SpotifyCatalogMapper)->likedSongs(songMapperFixture('me-tracks')['items'])[0];

    expect(array_keys($song->toArray()))
        ->toBe(['source', 'id', 'uri', 'title', 'artists', 'album', 'duration_ms', 'image', 'playable', 'added_at']);
});
