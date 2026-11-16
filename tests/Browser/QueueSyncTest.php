<?php

// playback-queue, task 4.5: failed edits, version conflicts and sync between devices (spec "playback-queue"). The
// "other device" writes straight to the test database, which the in-process browser server shares. The poll interval
// is shortened through config, and the waits are scaled to it.

use App\Models\PlaybackQueue;
use App\Models\PlaybackQueueItem;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Http::preventStrayRequests();

    config()->set('services.spotify', [
        'client_id' => 'client-abc',
        'client_secret' => 'secret-xyz',
        'owner_id' => 'owner-123',
        'redirect_uri' => 'http://127.0.0.1:8000/spotify/callback',
        'timeout' => 10,
        'sdk_url' => '',
    ]);

    Http::fake([
        'api.spotify.com/v1/search*' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/spotify/search-daft-punk.json')), true)),
    ]);
});

/**
 * What the other device does: add a song at the end of the owner's queue, raising the version as the API would.
 */
function syncOtherDeviceAdds(User $owner, string $title): void
{
    $queue = PlaybackQueue::query()->where('user_id', $owner->getKey())->firstOrFail();
    $song = queueSong($title);

    PlaybackQueueItem::factory()->create([
        'playback_queue_id' => $queue->getKey(),
        'position' => PlaybackQueueItem::query()->where('playback_queue_id', $queue->getKey())->count(),
        'song_id' => $song['id'],
        'uri' => $song['uri'],
        'title' => $title,
    ]);

    $queue->increment('version');
}

/**
 * @param  ArrayObject<int, string>  $log
 */
function syncReads(ArrayObject $log): int
{
    return collect($log)->filter(fn (string $line) => $line === 'GET /queue')->count();
}

const SYNC_ADD_ONE_MORE_TIME = '[data-part=queueable-song-row]:has([data-id="0DiWol3AO6WpXZgp0goxAV"]) [data-part=add-to-queue]';

test('The server fails during an edit', function (string $failure) {
    $owner = queueBrowserOwner();
    queueStored($owner, ['Song N', 'Song P1', 'Song P2'], version: 2);

    if ($failure === '503') {
        Route::post('queue/items', fn () => response()->json([], 503))->middleware(['web', 'auth']);
    }

    $page = queueBrowserVisit('/search')->fill('#search-query', 'Daft Punk')->wait(1.5);

    if ($failure === 'network') {
        $page->script('(() => {
            const original = window.fetch;
            window.fetch = (url, options) => String(url).includes("/queue/items") ? Promise.reject(new TypeError("Failed to fetch")) : original(url, options);
        })()');
    }

    $page->click(SYNC_ADD_ONE_MORE_TIME)->wait(1);

    expect(queueBrowserMessage($page))->toBe("Couldn't update the queue. Try again.")
        ->and(queueTitles($owner))->toBe(['Song N', 'Song P1', 'Song P2']);

    queueBrowserOpen($page);

    expect(queueBrowserTitles($page, 'next-up'))->toBe(['Song P1', 'Song P2']);
})->with([
    'the server answers 503' => ['503'],
    'the network is down' => ['network'],
]);

test('A stale version from another device', function () {
    $owner = queueBrowserOwner();
    queueStored($owner, ['Song N', 'Song P1', 'Song P2'], version: 7);
    $log = queueBrowserRequestLog();

    $page = queueBrowserVisit('/search')->fill('#search-query', 'Daft Punk')->wait(1.5);

    // The phone removes "Song P2": version 8.
    PlaybackQueueItem::query()->whereKey(queueItemId($owner, 'Song P2'))->delete();
    PlaybackQueue::query()->where('user_id', $owner->getKey())->update(['version' => 8]);

    $page->click(SYNC_ADD_ONE_MORE_TIME)->wait(1.5);

    expect(queueBrowserMessage($page))->toBe('The queue changed on another device.')
        ->and(collect($log)->filter(fn (string $line) => str_starts_with($line, 'POST /queue/items'))->values()->all())
        ->toBe(['POST /queue/items v=7'])
        ->and(queueTitles($owner))->toBe(['Song N', 'Song P1']);

    queueBrowserOpen($page);

    expect(queueBrowserTitles($page, 'next-up'))->toBe(['Song P1']);
});

test('The same queue on another device', function () {
    $owner = queueBrowserOwner();
    queueStored($owner, ['Song C', 'Song A', 'Song D'], current: 1);

    $page = queueBrowserOpen(queueBrowserVisit());

    expect(queueBrowserTitles($page, 'now-playing'))->toBe(['Song A'])
        ->and(queueBrowserTitles($page, 'next-up'))->toBe(['Song D']);
});

test('Returning to the tab shows changes from another device', function () {
    config()->set('player.queue_poll_seconds', 600);
    $owner = queueBrowserOwner();
    queueStored($owner, ['Song A', 'Song B'], version: 3);
    $log = queueBrowserRequestLog();

    $page = queueBrowserOpen(queueBrowserVisit());
    queueBrowserVisibility($page, 'hidden');
    $before = syncReads($log);

    syncOtherDeviceAdds($owner, 'Song E');
    queueBrowserVisibility($page, 'visible');
    $page->wait(1);

    expect(syncReads($log))->toBe($before + 1)
        ->and(queueBrowserTitles($page, 'next-up'))->toBe(['Song B', 'Song E']);
});

test('A visible tab reads the queue every 30 seconds', function () {
    // 2 s stand for 30 s, so 31 s scale to about 2.1 s.
    config()->set('player.queue_poll_seconds', 2);
    $owner = queueBrowserOwner();
    queueStored($owner, ['Song A', 'Song B'], version: 3);
    $log = queueBrowserRequestLog();

    $page = queueBrowserOpen(queueBrowserVisit());
    $before = syncReads($log);

    syncOtherDeviceAdds($owner, 'Song E');
    $page->wait(2.1);

    expect(syncReads($log) - $before)->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(2)
        ->and(queueBrowserTitles($page, 'next-up'))->toBe(['Song B', 'Song E']);
});

test('A hidden tab does not read the queue', function () {
    // 1 s stands for 30 s, so 90 s scale to 3 s.
    config()->set('player.queue_poll_seconds', 1);
    $owner = queueBrowserOwner();
    queueStored($owner, ['Song A']);
    $log = queueBrowserRequestLog();

    $page = queueBrowserVisit();
    queueBrowserVisibility($page, 'hidden');
    $page->wait(0.3);
    $before = syncReads($log);

    $page->wait(3);

    expect(syncReads($log))->toBe($before);
});
