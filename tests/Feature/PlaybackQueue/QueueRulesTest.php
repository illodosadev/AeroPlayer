<?php

// playback-queue, task 3.6: song validation, malformed writes, stale versions and the 500-song limit (spec
// "playback-queue"). No test reaches Spotify.

use App\Models\PlaybackQueueItem;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('An invalid song', function (array $overrides, array $without = []) {
    $owner = User::factory()->create();
    queueStored($owner, ['Song A'], version: 4);

    $song = array_diff_key(queueSong('Song X', $overrides), array_flip($without));

    $this->actingAs($owner)
        ->postJson('/queue/items', ['version' => 4, 'tracks' => [$song]])
        ->assertStatus(422)
        ->assertExactJson(['error' => 'invalid_track']);

    expect($this->actingAs($owner)->getJson('/queue')->json('version'))->toBe(4)
        ->and(queueTitles($owner))->toBe(['Song A']);
})->with([
    'uri "spotify:album:2noRn2Aes5aoNVsU6iWThc"' => [['id' => '2noRn2Aes5aoNVsU6iWThc', 'uri' => 'spotify:album:2noRn2Aes5aoNVsU6iWThc']],
    'uri "spotify:track:abc"' => [['id' => 'abc', 'uri' => 'spotify:track:abc']],
    'source "deezer"' => [['source' => 'deezer']],
    'playable false' => [['playable' => false]],
    'no title' => [[], ['title']],
    'an empty title' => [['title' => '']],
    'origin "own_playlist" (a text, not an object)' => [['origin' => 'own_playlist']],
    'an origin without a type' => [['origin' => ['playlist_id' => 12]]],
    'an origin over 1 KB' => [['origin' => ['type' => 'own_playlist', 'note' => str_repeat('x', 1100)]]],
    'an id that does not match the uri' => [['id' => 'abcdefghijklmnopqrstuv']],
]);

test('A malformed write', function (string $method, callable $uri, callable $body, string $code) {
    $owner = User::factory()->create();
    queueStored($owner, ['Song A', 'Song B'], current: 0, version: 4);

    $this->actingAs($owner)
        ->json($method, $uri($owner), $body($owner))
        ->assertStatus(422)
        ->assertExactJson(['error' => $code]);

    expect($this->actingAs($owner)->getJson('/queue')->json('version'))->toBe(4)
        ->and(queueTitles($owner))->toBe(['Song A', 'Song B']);
})->with([
    'POST /queue/items with a song and no version' => [
        'POST', fn () => '/queue/items', fn () => ['tracks' => [queueSong('Song X')]], 'invalid_request',
    ],
    'POST /queue/items with version 4 and "tracks" empty' => [
        'POST', fn () => '/queue/items', fn () => ['version' => 4, 'tracks' => []], 'tracks_required',
    ],
    'PATCH /queue/items/{item} with version "four" and position 0' => [
        'PATCH', fn (User $owner) => '/queue/items/'.queueItemId($owner, 'Song B'), fn () => ['version' => 'four', 'position' => 0], 'invalid_request',
    ],
    'PATCH /queue with version 4 and no field to change' => [
        'PATCH', fn () => '/queue', fn () => ['version' => 4], 'invalid_request',
    ],
    'DELETE /queue/items/{item} without a version' => [
        'DELETE', fn (User $owner) => '/queue/items/'.queueItemId($owner, 'Song B'), fn () => [], 'invalid_request',
    ],
]);

test('A change from another device', function () {
    $owner = User::factory()->create();
    queueStored($owner, ['Song A', 'Song B', 'Song C'], current: 0, version: 7);

    // The phone removes a song: version 8.
    $this->actingAs($owner)->deleteJson('/queue/items/'.queueItemId($owner, 'Song C').'?version=7')->assertOk();

    // The laptop still holds version 7.
    $response = $this->actingAs($owner)
        ->postJson('/queue/items', ['version' => 7, 'tracks' => [queueSong('Song X')]])
        ->assertStatus(409);

    expect($response->json('error'))->toBe('queue_version_conflict')
        ->and($response->json('queue.version'))->toBe(8)
        ->and(array_column($response->json('queue.items'), 'title'))->toBe(['Song A', 'Song B'])
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and(queueTitles($owner))->not->toContain('Song X');
});

test('Two writes with the same version', function () {
    $owner = User::factory()->create();
    queueStored($owner, ['Song A'], version: 3);

    $first = $this->actingAs($owner)->postJson('/queue/items', ['version' => 3, 'tracks' => [queueSong('Song X')]]);
    $second = $this->actingAs($owner)->postJson('/queue/items', ['version' => 3, 'tracks' => [queueSong('Song Y')]]);

    expect($first->status())->toBe(200)
        ->and($first->json('version'))->toBe(4)
        ->and($second->status())->toBe(409)
        ->and($second->json('error'))->toBe('queue_version_conflict');
});

test('a stale version is checked before the item exists', function () {
    $owner = User::factory()->create();
    queueStored($owner, ['Song A'], version: 3);

    $this->actingAs($owner)->deleteJson('/queue/items/99999?version=2')->assertStatus(409);
});

test('The queue is full', function () {
    $owner = User::factory()->create();
    $queue = queueStored($owner, ['Song A'], version: 1);
    PlaybackQueueItem::factory()->count(499)->sequence(fn ($sequence) => ['position' => $sequence->index + 1])
        ->create(['playback_queue_id' => $queue->getKey()]);

    $this->actingAs($owner)
        ->postJson('/queue/items', ['version' => 1, 'tracks' => [queueSong('Song X')]])
        ->assertStatus(422)
        ->assertExactJson(['error' => 'queue_full']);

    expect(PlaybackQueueItem::query()->where('playback_queue_id', $queue->getKey())->count())->toBe(500)
        ->and($this->actingAs($owner)->getJson('/queue')->json('version'))->toBe(1);
});
