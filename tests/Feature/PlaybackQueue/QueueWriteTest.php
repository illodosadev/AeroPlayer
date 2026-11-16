<?php

// playback-queue, task 3.4: adding, replacing and moving the current song (spec "playback-queue"). No test reaches
// Spotify.

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('Adding a song saves it and raises the version', function () {
    $owner = User::factory()->create();
    queueStored($owner, ['Song A', 'Song B'], version: 4);

    $song = queueSong('Song X', ['id' => '0DiWol3AO6WpXZgp0goxAV', 'uri' => 'spotify:track:0DiWol3AO6WpXZgp0goxAV']);

    $response = $this->actingAs($owner)->postJson('/queue/items', ['version' => 4, 'tracks' => [$song]])->assertOk();

    expect($response->json('version'))->toBe(5)
        ->and($response->json('items'))->toHaveCount(3)
        ->and($response->json('items.2.uri'))->toBe('spotify:track:0DiWol3AO6WpXZgp0goxAV')
        ->and($response->json('items.2.origin'))->toBeNull()
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');

    Http::assertNothingSent();
});

test('Adding to an empty queue makes the song current', function () {
    $owner = User::factory()->create();

    $response = $this->actingAs($owner)->postJson('/queue/items', ['version' => 0, 'tracks' => [queueSong('Song X')]])->assertOk();

    expect($response->json('version'))->toBe(1)
        ->and($response->json('current_item_id'))->toBe(queueItemId($owner, 'Song X'));
});

test('adding to a queue whose current song was removed makes the song current', function () {
    $owner = User::factory()->create();
    queueStored($owner, ['Song A'], current: null, version: 2);

    $response = $this->actingAs($owner)->postJson('/queue/items', ['version' => 2, 'tracks' => [queueSong('Song X')]])->assertOk();

    expect($response->json('current_item_id'))->toBe(queueItemId($owner, 'Song X'));
});

test('The origin is kept as it is', function () {
    $owner = User::factory()->create();
    $origin = ['type' => 'own_playlist', 'playlist_id' => 12, 'item_id' => 345];

    $this->actingAs($owner)->putJson('/queue', ['tracks' => [queueSong('Song A', ['origin' => $origin])]])->assertOk();

    expect($this->actingAs($owner)->getJson('/queue')->json('items.0.origin'))->toBe($origin);
});

test('The same song twice', function () {
    $owner = User::factory()->create();
    queueStored($owner, ['Song A'], version: 1);

    $response = $this->actingAs($owner)->postJson('/queue/items', ['version' => 1, 'tracks' => [queueSong('Song A')]])->assertOk();

    expect(array_column($response->json('items'), 'title'))->toBe(['Song A', 'Song A'])
        ->and($response->json('items.0.item_id'))->not->toBe($response->json('items.1.item_id'));
});

test('extra keys of a song are ignored', function () {
    $owner = User::factory()->create();

    $response = $this->actingAs($owner)
        ->postJson('/queue/items', ['version' => 0, 'tracks' => [queueSong('Song A', ['position' => 4, 'local' => false, 'added_at' => '2026-01-01T00:00:00Z'])]])
        ->assertOk();

    expect(array_keys($response->json('items.0')))->toBe(QUEUE_ITEM_KEYS);
});

test('Replacing with an old version', function () {
    $owner = User::factory()->create();
    queueStored($owner, ['Old A', 'Old B'], version: 9);

    $response = $this->actingAs($owner)
        ->putJson('/queue', ['tracks' => [queueSong('Song X'), queueSong('Song Y'), queueSong('Song Z')], 'current_index' => 1])
        ->assertOk();

    expect($response->json('version'))->toBe(10)
        ->and(array_column($response->json('items'), 'title'))->toBe(['Song X', 'Song Y', 'Song Z'])
        ->and($response->json('current_item_id'))->toBe(queueItemId($owner, 'Song Y'));
});

test('replacing without an index makes the first song current', function () {
    $owner = User::factory()->create();

    $response = $this->actingAs($owner)->putJson('/queue', ['tracks' => [queueSong('Song X'), queueSong('Song Y')]])->assertOk();

    expect($response->json('current_item_id'))->toBe(queueItemId($owner, 'Song X'));
});

test('A replacement that cannot apply', function (callable $input, string $code) {
    $owner = User::factory()->create();
    queueStored($owner, ['Song A', 'Song B'], version: 6);

    $this->actingAs($owner)->putJson('/queue', $input())->assertStatus(422)->assertExactJson(['error' => $code]);

    expect($this->actingAs($owner)->getJson('/queue')->json('version'))->toBe(6)
        ->and(queueTitles($owner))->toBe(['Song A', 'Song B']);
})->with([
    '"tracks" empty' => [fn () => ['tracks' => []], 'tracks_required'],
    '3 songs and current_index 3' => [fn () => ['tracks' => [queueSong('X'), queueSong('Y'), queueSong('Z')], 'current_index' => 3], 'current_index_out_of_range'],
    '501 songs' => [fn () => ['tracks' => array_map(fn (int $i) => queueSong("Song {$i}"), range(1, 501))], 'queue_full'],
]);

test('Moving forward marks the skipped songs as played', function () {
    $owner = User::factory()->create();
    queueStored($owner, ['Song A', 'Song B', 'Song C', 'Song D'], current: 0, version: 3);

    $response = $this->actingAs($owner)
        ->patchJson('/queue', ['version' => 3, 'current_item_id' => queueItemId($owner, 'Song C')])
        ->assertOk();

    expect($response->json('version'))->toBe(4)
        ->and($response->json('current_item_id'))->toBe(queueItemId($owner, 'Song C'))
        ->and(array_column($response->json('items'), 'title'))->toBe(['Song A', 'Song B', 'Song C', 'Song D']);
});

test('Moving to an unknown song', function () {
    $owner = User::factory()->create();
    queueStored($owner, ['Song A'], version: 3);

    $this->actingAs($owner)
        ->patchJson('/queue', ['version' => 3, 'current_item_id' => 99999])
        ->assertStatus(404)
        ->assertExactJson(['error' => 'queue_item_not_found']);

    expect($this->actingAs($owner)->getJson('/queue')->json('version'))->toBe(3);
});
