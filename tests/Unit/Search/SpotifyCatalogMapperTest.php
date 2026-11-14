<?php

// spotify-search-api, task 1.1: "A song with every detail", "Missing images and playability", the album year, null
// items and the exact keys of each item (spec "Results use the app's common format", design D2).

use App\Spotify\SpotifyCatalogMapper;

/**
 * The trimmed Spotify search answer for "Daft Punk" (design D5).
 *
 * @return array<string, mixed>
 */
function mapperFixture(): array
{
    return json_decode(file_get_contents(dirname(__DIR__, 2).'/Fixtures/spotify/search-daft-punk.json'), true);
}

test('A song with every detail', function () {
    $song = (new SpotifyCatalogMapper)->songs(mapperFixture()['tracks']['items'])[0];

    expect($song->toArray())->toBe([
        'source' => 'spotify',
        'id' => '0DiWol3AO6WpXZgp0goxAV',
        'uri' => 'spotify:track:0DiWol3AO6WpXZgp0goxAV',
        'title' => 'One More Time',
        'artists' => ['Daft Punk'],
        'album' => 'Discovery',
        'duration_ms' => 320000,
        'image' => 'https://i.scdn.co/image/ab67616d0000b273b33d46dfa2635a47eebf63b2',
        'playable' => true,
    ]);
});

test('Missing images and playability', function () {
    $fixture = mapperFixture();
    $mapper = new SpotifyCatalogMapper;

    $artist = [...$fixture['artists']['items'][0], 'images' => []];
    $notPlayable = $fixture['tracks']['items'][1];
    $withoutField = $fixture['tracks']['items'][2];

    expect($notPlayable['is_playable'])->toBeFalse()
        ->and($withoutField)->not->toHaveKey('is_playable');

    expect($mapper->artists([$artist])[0]->image)->toBeNull();

    [$first, $second] = $mapper->songs([$notPlayable, $withoutField]);

    expect($first->playable)->toBeFalse()
        ->and($second->playable)->toBeTrue();
});

test('a song or album without a cover has a null image', function () {
    $fixture = mapperFixture();
    $mapper = new SpotifyCatalogMapper;

    $track = $fixture['tracks']['items'][0];
    $track['album']['images'] = [];
    $album = $fixture['albums']['items'][0];
    unset($album['images']);

    expect($mapper->songs([$track])[0]->image)->toBeNull()
        ->and($mapper->albums([$album])[0]->image)->toBeNull();
});

test('the album year is the first four digits of the release date', function (?string $releaseDate, ?int $year) {
    $album = [...mapperFixture()['albums']['items'][0], 'release_date' => $releaseDate];

    expect((new SpotifyCatalogMapper)->albums([$album])[0]->year)->toBe($year);
})->with([
    'a day' => ['2001-03-12', 2001],
    'a month' => ['2001-03', 2001],
    'a year' => ['1997', 1997],
    'no date' => [null, null],
    'an unreadable date' => ['unknown', null],
]);

test('null items are skipped', function () {
    $fixture = mapperFixture();
    $mapper = new SpotifyCatalogMapper;

    $songs = $mapper->songs([null, $fixture['tracks']['items'][0], null]);
    $albums = $mapper->albums([null, ...$fixture['albums']['items']]);
    $artists = $mapper->artists([$fixture['artists']['items'][0], null]);

    expect($songs)->toHaveCount(1)
        ->and($songs[0]->title)->toBe('One More Time')
        ->and($albums)->toHaveCount(2)
        ->and($artists)->toHaveCount(1);
});

test('every item has exactly the common keys', function () {
    $fixture = mapperFixture();
    $mapper = new SpotifyCatalogMapper;

    foreach ($mapper->songs($fixture['tracks']['items']) as $song) {
        expect(array_keys($song->toArray()))
            ->toBe(['source', 'id', 'uri', 'title', 'artists', 'album', 'duration_ms', 'image', 'playable']);
    }

    foreach ($mapper->albums($fixture['albums']['items']) as $album) {
        expect(array_keys($album->toArray()))->toBe(['source', 'id', 'uri', 'title', 'artists', 'year', 'image']);
    }

    foreach ($mapper->artists($fixture['artists']['items']) as $artist) {
        expect(array_keys($artist->toArray()))->toBe(['source', 'id', 'uri', 'title', 'image']);
    }

    expect($mapper->albums($fixture['albums']['items'])[1]->toArray())->toBe([
        'source' => 'spotify',
        'id' => '5uRdvUR7xCnHmUW8n64n9y',
        'uri' => 'spotify:album:5uRdvUR7xCnHmUW8n64n9y',
        'title' => 'Homework',
        'artists' => ['Daft Punk'],
        'year' => 1997,
        'image' => 'https://i.scdn.co/image/ab67616d0000b2738ac778cc7d88779f74d33311',
    ]);
});
