<?php

// Shared helpers of the playback-queue tests (feature and browser), loaded from tests/Pest.php.

use App\Models\PlaybackQueue;
use App\Models\PlaybackQueueItem;
use App\Models\SpotifyConnection;
use App\Models\User;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

/**
 * The exact keys of a queue song in the API (spec "The queue is read in full").
 */
const QUEUE_ITEM_KEYS = ['item_id', 'source', 'id', 'uri', 'title', 'artists', 'album', 'duration_ms', 'image', 'origin'];

/**
 * A Spotify song in the app's common format, with a 22-character id made from its title, as the rows send it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function queueSong(string $title, array $overrides = []): array
{
    $id = queueSongId($title);

    return [
        'source' => 'spotify',
        'id' => $id,
        'uri' => "spotify:track:{$id}",
        'title' => $title,
        'artists' => ["{$title} Artist"],
        'album' => "{$title} Album",
        'duration_ms' => 200000,
        'image' => null,
        'playable' => true,
        ...$overrides,
    ];
}

function queueSongId(string $title): string
{
    return substr(str_pad((string) preg_replace('/[^A-Za-z0-9]/', '', $title), 22, '0'), 0, 22);
}

/**
 * Store a queue for the owner with these songs in order, the one at $current as the current song (null for none).
 *
 * @param  list<string>  $titles
 */
function queueStored(User $owner, array $titles, ?int $current = 0, int $version = 1): PlaybackQueue
{
    $queue = PlaybackQueue::factory()->for($owner)->create(['version' => $version]);

    $ids = [];

    foreach ($titles as $position => $title) {
        $song = queueSong($title);
        $ids[] = PlaybackQueueItem::factory()->create([
            'playback_queue_id' => $queue->getKey(),
            'position' => $position,
            'original_position' => $position,
            'song_id' => $song['id'],
            'uri' => $song['uri'],
            'title' => $title,
            'artists' => $song['artists'],
            'album' => $song['album'],
            'duration_ms' => $song['duration_ms'],
        ])->getKey();
    }

    $queue->update(['current_item_id' => $current === null ? null : $ids[$current]]);

    return $queue->fresh();
}

/**
 * The item id of the song with this title in the owner's queue.
 */
function queueItemId(User $owner, string $title): int
{
    return (int) PlaybackQueueItem::query()
        ->whereRelation('queue', 'user_id', $owner->getKey())
        ->where('title', $title)
        ->value('id');
}

// --- Browser helpers ---------------------------------------------------------------------------------------------

const QUEUE_FAKE_SDK_PATH = '/testing/queue-fake-spotify-sdk.js';

/**
 * The owner of the browser tests, with a Spotify connection unless $connected is false. With $player, the pages load
 * the fake SDK.
 */
function queueBrowserOwner(bool $connected = true, bool $player = false): User
{
    config()->set('services.spotify.sdk_url', $player ? QUEUE_FAKE_SDK_PATH : '');

    Route::get(QUEUE_FAKE_SDK_PATH, fn () => response()->file(
        base_path('tests/Browser/fixtures/fake-spotify-sdk.js'),
        ['Content-Type' => 'application/javascript'],
    ));

    $owner = User::factory()->create(['email' => 'owner@example.com', 'password' => 'Correct-Horse-42']);

    if ($connected) {
        SpotifyConnection::factory()->for($owner)->create(['access_token_expires_at' => now()->addHour()]);
    }

    return $owner;
}

/**
 * Sign the owner in and open a page.
 */
function queueBrowserVisit(string $path = '/dashboard')
{
    return visit('/login')
        ->fill('email', 'owner@example.com')
        ->fill('password', 'Correct-Horse-42')
        ->press('Log in')
        ->wait(2)
        ->navigate($path)
        ->wait(1);
}

/**
 * Open the queue panel from the player bar.
 */
function queueBrowserOpen($page)
{
    return $page->click('[data-part=queue-toggle]')->wait(0.5);
}

/**
 * The titles a section of the panel shows: "now-playing" or "next-up".
 *
 * @return list<string>
 */
function queueBrowserTitles($page, string $section): array
{
    return $page->script('[...document.querySelectorAll("#queue-panel [data-section='.$section.'] [data-part=title]")].map((el) => el.textContent.trim())');
}

/**
 * The text of the queue's status line.
 */
function queueBrowserMessage($page): string
{
    return (string) $page->script('document.querySelector("[data-part=queue-message]")?.textContent.trim() ?? ""');
}

/**
 * Record the method and path of every request the app handles, so a test can count them on the server.
 *
 * @return ArrayObject<int, string>
 */
function queueBrowserRequestLog(): ArrayObject
{
    $log = new ArrayObject;

    Event::listen(
        RequestHandled::class,
        fn ($event) => $log->append(trim($event->request->method().' /'.ltrim($event->request->path(), '/')
            .($event->request->has('version') ? ' v='.$event->request->input('version') : ''))),
    );

    return $log;
}

/**
 * Pretend the tab is hidden or visible again, as the browser does when the owner switches tabs.
 */
function queueBrowserVisibility($page, string $state): void
{
    $page->script('(() => {
        Object.defineProperty(document, "visibilityState", { configurable: true, get: () => "'.$state.'" });
        document.dispatchEvent(new Event("visibilitychange"));
    })()');
}

/**
 * The titles of the owner's queue, in order.
 *
 * @return list<string>
 */
function queueTitles(User $owner): array
{
    return PlaybackQueueItem::query()
        ->whereRelation('queue', 'user_id', $owner->getKey())
        ->orderBy('position')
        ->pluck('title')
        ->all();
}
