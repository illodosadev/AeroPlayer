<?php

use App\Application\PlaybackQueue\QueueTrack;
use App\Models\PlaybackQueue;
use App\Models\PlaybackQueueItem;
use App\Models\User;
use App\Repositories\PlaybackQueueItemRepository;
use App\Repositories\PlaybackQueueRepository;

// The repositories behind the queue (playback-queue, design D2 and D3). They need the database, so they live with the
// feature tests.

function repoTrack(string $title): QueueTrack
{
    $id = str_pad(preg_replace('/[^A-Za-z0-9]/', '', $title), 22, 'x');

    return new QueueTrack('spotify', $id, "spotify:track:{$id}", $title, ['Artist'], 'Album', 180000, null, null);
}

/**
 * @return list<string>
 */
function repoTitles(PlaybackQueue $queue): array
{
    return app(PlaybackQueueItemRepository::class)->forQueue($queue->getKey())->pluck('title')->all();
}

/**
 * @return list<int>
 */
function repoPositions(PlaybackQueue $queue): array
{
    return app(PlaybackQueueItemRepository::class)->forQueue($queue->getKey())->pluck('position')->all();
}

test('appending numbers the songs after the existing ones and copies the position to original_position', function () {
    $queue = PlaybackQueue::factory()->create();
    $items = app(PlaybackQueueItemRepository::class);

    $items->append($queue->getKey(), [repoTrack('Song A'), repoTrack('Song B')]);
    $ids = $items->append($queue->getKey(), [repoTrack('Song C')]);

    expect(repoTitles($queue))->toBe(['Song A', 'Song B', 'Song C'])
        ->and(repoPositions($queue))->toBe([0, 1, 2])
        ->and(PlaybackQueueItem::query()->find($ids[0])->original_position)->toBe(2);
});

test('moving a song keeps the others in order and the positions dense', function () {
    $queue = PlaybackQueue::factory()->create();
    $items = app(PlaybackQueueItemRepository::class);
    $ids = $items->append($queue->getKey(), [repoTrack('Song A'), repoTrack('Song B'), repoTrack('Song C'), repoTrack('Song D')]);

    $items->moveTo($queue, $items->find($queue->getKey(), $ids[3]), 1);

    expect(repoTitles($queue))->toBe(['Song A', 'Song D', 'Song B', 'Song C'])
        ->and(repoPositions($queue))->toBe([0, 1, 2, 3]);
});

test('removing a song closes the gap', function () {
    $queue = PlaybackQueue::factory()->create();
    $items = app(PlaybackQueueItemRepository::class);
    $ids = $items->append($queue->getKey(), [repoTrack('Song A'), repoTrack('Song B'), repoTrack('Song C')]);

    $items->delete($items->find($queue->getKey(), $ids[1]));

    expect(repoTitles($queue))->toBe(['Song A', 'Song C'])
        ->and(repoPositions($queue))->toBe([0, 1]);
});

test('replacing removes every song and numbers the new ones from 0', function () {
    $queue = PlaybackQueue::factory()->create();
    $items = app(PlaybackQueueItemRepository::class);
    $items->append($queue->getKey(), [repoTrack('Old A'), repoTrack('Old B')]);

    $items->replaceAll($queue->getKey(), [repoTrack('Song X'), repoTrack('Song Y')]);

    expect(repoTitles($queue))->toBe(['Song X', 'Song Y'])
        ->and(repoPositions($queue))->toBe([0, 1]);
});

test('the song after the current one is the next, and the last has none', function () {
    $queue = PlaybackQueue::factory()->create();
    $items = app(PlaybackQueueItemRepository::class);
    $ids = $items->append($queue->getKey(), [repoTrack('Song A'), repoTrack('Song B')]);

    expect($items->nextAfter($items->find($queue->getKey(), $ids[0]))?->getKey())->toBe($ids[1])
        ->and($items->nextAfter($items->find($queue->getKey(), $ids[1])))->toBeNull();
});

test('an item of another queue is not found', function () {
    $queue = PlaybackQueue::factory()->create();
    $other = PlaybackQueueItem::factory()->create();

    expect(app(PlaybackQueueItemRepository::class)->find($queue->getKey(), $other->getKey()))->toBeNull();
});

test('a write creates the queue when it is missing and raises the version', function () {
    $user = User::factory()->create();
    $queues = app(PlaybackQueueRepository::class);

    $queues->writeForOwner($user->getKey(), fn (PlaybackQueue $queue) => null);
    $queues->writeForOwner($user->getKey(), fn (PlaybackQueue $queue) => null);

    expect(PlaybackQueue::query()->where('user_id', $user->getKey())->count())->toBe(1)
        ->and($queues->stateForOwner($user->getKey())->version)->toBe(2);
});

test('a write that throws keeps nothing and the version stays', function () {
    $queue = PlaybackQueue::factory()->create(['version' => 4]);
    $queues = app(PlaybackQueueRepository::class);

    expect(fn () => $queues->writeForOwner($queue->user_id, function (PlaybackQueue $locked) {
        app(PlaybackQueueItemRepository::class)->append($locked->getKey(), [repoTrack('Song A')]);

        throw new RuntimeException('rejected');
    }))->toThrow(RuntimeException::class);

    expect($queues->stateForOwner($queue->user_id)->version)->toBe(4)
        ->and(PlaybackQueueItem::query()->count())->toBe(0);
});

test('the state of an owner who never used the queue is null', function () {
    expect(app(PlaybackQueueRepository::class)->stateForOwner(User::factory()->create()->getKey()))->toBeNull();
});
