<?php

// playback-queue, task 3.5: moving pending songs and removing songs (spec "playback-queue"). No test reaches Spotify.

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('Moving a song to the front of the pending songs', function () {
    $owner = User::factory()->create();
    queueStored($owner, ['Song A', 'Song B', 'Song C', 'Song D'], current: 0, version: 2);

    $response = $this->actingAs($owner)
        ->patchJson('/queue/items/'.queueItemId($owner, 'Song D'), ['version' => 2, 'position' => 0])
        ->assertOk();

    expect($response->json('version'))->toBe(3)
        ->and(array_column($response->json('items'), 'title'))->toBe(['Song A', 'Song D', 'Song B', 'Song C']);
});

test('moving a song to the end of the pending songs', function () {
    $owner = User::factory()->create();
    queueStored($owner, ['Song P', 'Song A', 'Song B', 'Song C'], current: 1, version: 2);

    $response = $this->actingAs($owner)
        ->patchJson('/queue/items/'.queueItemId($owner, 'Song B'), ['version' => 2, 'position' => 1])
        ->assertOk();

    expect(array_column($response->json('items'), 'title'))->toBe(['Song P', 'Song A', 'Song C', 'Song B']);
});

test('A move that cannot apply', function (string $item, int $position, int $status, string $code) {
    $owner = User::factory()->create();
    queueStored($owner, ['Song A', 'Song B', 'Song C', 'Song D', 'Song E'], current: 1, version: 6);

    $id = $item === 'missing' ? 99999 : queueItemId($owner, $item);

    $this->actingAs($owner)
        ->patchJson("/queue/items/{$id}", ['version' => 6, 'position' => $position])
        ->assertStatus($status)
        ->assertExactJson(['error' => $code]);

    expect($this->actingAs($owner)->getJson('/queue')->json('version'))->toBe(6)
        ->and(queueTitles($owner))->toBe(['Song A', 'Song B', 'Song C', 'Song D', 'Song E']);
})->with([
    'an item that does not exist' => ['missing', 0, 404, 'queue_item_not_found'],
    '"Song B", the current one' => ['Song B', 0, 422, 'item_not_pending'],
    '"Song A", already played' => ['Song A', 0, 422, 'item_not_pending'],
    '"Song E" to position 3' => ['Song E', 3, 422, 'position_out_of_range'],
]);

test('Removing a pending song', function () {
    $owner = User::factory()->create();
    queueStored($owner, ['Song A', 'Song B', 'Song C'], current: 0, version: 5);

    $response = $this->actingAs($owner)
        ->deleteJson('/queue/items/'.queueItemId($owner, 'Song B').'?version=5')
        ->assertOk();

    expect($response->json('version'))->toBe(6)
        ->and(array_column($response->json('items'), 'title'))->toBe(['Song A', 'Song C'])
        ->and(array_column($response->json('items'), 'item_id'))->toBe([queueItemId($owner, 'Song A'), queueItemId($owner, 'Song C')]);
});

test('Removing the current song', function () {
    $owner = User::factory()->create();
    queueStored($owner, ['Song A', 'Song B', 'Song C'], current: 0, version: 5);

    $response = $this->actingAs($owner)
        ->deleteJson('/queue/items/'.queueItemId($owner, 'Song A').'?version=5')
        ->assertOk();

    expect($response->json('current_item_id'))->toBe(queueItemId($owner, 'Song B'));
});

test('Removing the last song, which is the current one', function () {
    $owner = User::factory()->create();
    queueStored($owner, ['Song P', 'Song A'], current: 1, version: 5);

    $response = $this->actingAs($owner)
        ->deleteJson('/queue/items/'.queueItemId($owner, 'Song A').'?version=5')
        ->assertOk();

    expect($response->json('current_item_id'))->toBeNull()
        ->and(array_column($response->json('items'), 'title'))->toBe(['Song P']);
});

test('Removing an unknown song', function () {
    $owner = User::factory()->create();
    queueStored($owner, ['Song A'], version: 5);

    $this->actingAs($owner)
        ->deleteJson('/queue/items/99999?version=5')
        ->assertStatus(404)
        ->assertExactJson(['error' => 'queue_item_not_found']);

    expect($this->actingAs($owner)->getJson('/queue')->json('version'))->toBe(5);
});

test('a song of another owner cannot be removed', function () {
    $owner = User::factory()->create();
    queueStored($owner, ['Song A'], version: 1);
    $other = User::factory()->create();
    queueStored($other, ['Other Song'], version: 1);

    $this->actingAs($owner)
        ->deleteJson('/queue/items/'.queueItemId($other, 'Other Song').'?version=1')
        ->assertStatus(404);

    expect(queueTitles($other))->toBe(['Other Song']);
});
