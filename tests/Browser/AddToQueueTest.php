<?php

// playback-queue, task 4.4: "Add to queue" on the song rows of the search, Liked Songs and own playlist pages (spec
// "playback-queue"). Spotify is faked on the server; the browser server runs in the test process, so the queue is
// checked in the test database and Http::recorded() shows what reached Spotify.

use App\Models\PlaybackQueueItem;
use Illuminate\Support\Facades\Http;

const ADD_QUEUE_PLAYLIST_URL = '/library/playlists/spotify/3cEYpjA9oz9GiPac4AsH4n';

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

    $fixture = fn (string $name) => json_decode(file_get_contents(base_path("tests/Fixtures/spotify/{$name}.json")), true);
    $items = $fixture('playlist-items');

    Http::fake([
        'api.spotify.com/v1/search*' => Http::response($fixture('search-daft-punk')),
        'api.spotify.com/v1/me/tracks*' => Http::response($fixture('me-tracks')),
        'api.spotify.com/v1/playlists/*/items*' => Http::response([...$items, 'next' => null]),
        'api.spotify.com/v1/playlists/*' => Http::response([...$fixture('playlist'), 'items' => ['total' => count($items['items'])]]),
        'api.spotify.com/v1/me/player/play*' => Http::response(null, 204),
    ]);
});

/**
 * Open the page of an origin and return it with the row's song id and title.
 *
 * @return array{0: mixed, 1: string, 2: string}
 */
function addQueueOpen(string $origin): array
{
    return match ($origin) {
        'search' => [queueBrowserVisit('/search')->fill('#search-query', 'Daft Punk')->wait(1.5), '0DiWol3AO6WpXZgp0goxAV', 'One More Time'],
        'liked' => [queueBrowserVisit('/library/liked'), '0VjIjW4GlUZAMYd2vXMi3b', 'Song Y'],
        'playlist' => [queueBrowserVisit(ADD_QUEUE_PLAYLIST_URL), '7ouMYWpwJ422jRcDASZB7P', 'Song B'],
    };
}

function addQueueButton(string $songId): string
{
    return '[data-part=queueable-song-row]:has([data-id="'.$songId.'"]) [data-part=add-to-queue]';
}

/**
 * Hold the answer to "POST /queue/items" in the page for a moment, as a slow network would.
 */
function addQueueSlowPosts($page, int $ms = 1500): void
{
    $page->script('(() => {
        const original = window.fetch;
        window.fetch = (url, options) => String(url).includes("/queue/items") && options?.method === "POST"
            ? new Promise((resolve, reject) => setTimeout(() => original(url, options).then(resolve, reject), '.$ms.'))
            : original(url, options);
    })()');
}

test('Adding a song from a row', function (string $origin) {
    $owner = queueBrowserOwner(player: true);
    queueStored($owner, ['Song N', 'Song P1', 'Song P2'], version: 2);

    [$page, $id, $title] = addQueueOpen($origin);
    $page->script('window.__fakeSpotify.emit("ready", {device_id: "web-1"})');
    $page->script('window.__fakeSpotify.emit("player_state_changed", {
        position: 10000, duration: 180000, paused: false, timestamp: Date.now(),
        track_window: { current_track: { uri: "spotify:track:a", name: "Track A", duration_ms: 180000,
            artists: [{name: "Artist 1"}], album: {name: "Album", images: []} }, next_tracks: [] }
    })');

    $page->click(addQueueButton($id))->wait(1);

    expect(queueBrowserMessage($page))->toBe('Added to queue')
        ->and(queueTitles($owner))->toBe(['Song N', 'Song P1', 'Song P2', $title])
        ->and($page->script('window.__fakeSpotify.callNames().filter((name) => ["pause", "resume", "togglePlay"].includes(name))'))->toBe([]);

    queueBrowserOpen($page);

    expect(queueBrowserTitles($page, 'next-up'))->toBe(['Song P1', 'Song P2', $title])
        ->and(Http::recorded()->filter(fn (array $pair) => str_contains($pair[0]->url(), '/v1/me/player/play')))->toHaveCount(0);
})->with([
    'the search results' => ['search'],
    'the Liked Songs page' => ['liked'],
    'an own playlist page' => ['playlist'],
]);

test('Songs that cannot play cannot be added', function () {
    queueBrowserOwner();

    [$page] = addQueueOpen('search');

    expect($page->script('[...document.querySelectorAll("[data-section=songs] [data-part=add-to-queue]")].map((b) => b.disabled)'))
        ->toBe([false, true, false]);
});

test('A double click adds the song once', function () {
    $owner = queueBrowserOwner();
    $log = queueBrowserRequestLog();

    [$page, $id, $title] = addQueueOpen('search');

    // Two clicks in the same task: the second lands before the button re-renders.
    $page->script('(() => { const b = document.querySelector(\''.addQueueButton($id).'\'); b.click(); b.click(); })()');
    $page->wait(1.5);

    expect(collect($log)->filter(fn (string $line) => str_starts_with($line, 'POST /queue/items'))->count())->toBe(1)
        ->and(queueTitles($owner))->toBe([$title]);
});

test('Adding does not play the row', function () {
    queueBrowserOwner(player: true);

    [$page, $id] = addQueueOpen('search');
    $page->script('window.__fakeSpotify.emit("ready", {device_id: "web-1"})');

    $page->click(addQueueButton($id))->wait(1);

    expect(Http::recorded()->filter(fn (array $pair) => str_contains($pair[0]->url(), '/v1/me/player/play')))->toHaveCount(0)
        ->and($page->script('window.__fakeSpotify.callNames().includes("activateElement")'))->toBeFalse();
});

test('Adding with the keyboard keeps the focus', function () {
    $owner = queueBrowserOwner();
    $log = queueBrowserRequestLog();

    [$page, $id, $title] = addQueueOpen('search');
    addQueueSlowPosts($page);

    $button = addQueueButton($id);
    $page->script('document.querySelector(\''.$button.'\').focus()');
    $page->keys($button, ['Enter'])->wait(0.3);

    expect($page->script('document.activeElement === document.querySelector(\''.$button.'\')'))->toBeTrue()
        ->and($page->script('document.querySelector(\''.$button.'\').getAttribute("aria-disabled")'))->toBe('true');

    // A second Enter before the answer does nothing.
    $page->keys($button, ['Enter'])->wait(2);

    expect($page->script('document.activeElement === document.querySelector(\''.$button.'\')'))->toBeTrue()
        ->and($page->script('document.querySelector(\''.$button.'\').hasAttribute("aria-disabled")'))->toBeFalse()
        ->and(collect($log)->filter(fn (string $line) => str_starts_with($line, 'POST /queue/items'))->count())->toBe(1)
        ->and(queueTitles($owner))->toBe([$title]);
});

test('Adding two songs from two rows in a row', function () {
    $owner = queueBrowserOwner();
    queueStored($owner, ['Song N'], version: 2);
    $log = queueBrowserRequestLog();

    [$page] = addQueueOpen('search');
    addQueueSlowPosts($page, 800);

    $page->click(addQueueButton('0DiWol3AO6WpXZgp0goxAV'));
    $page->click(addQueueButton('1pKYYY0dkg23sQQXi0Q5zN'))->wait(3);

    expect(collect($log)->filter(fn (string $line) => str_starts_with($line, 'POST /queue/items'))->values()->all())
        ->toBe(['POST /queue/items v=2', 'POST /queue/items v=3'])
        ->and(queueTitles($owner))->toBe(['Song N', 'One More Time', 'Around the World'])
        ->and(queueBrowserMessage($page))->not->toBe('The queue changed on another device.');
});

test('The queue is full', function () {
    $owner = queueBrowserOwner();
    $queue = queueStored($owner, ['Song N'], version: 1);
    PlaybackQueueItem::factory()->count(499)->sequence(fn ($sequence) => ['position' => $sequence->index + 1])
        ->create(['playback_queue_id' => $queue->getKey()]);

    [$page, $id] = addQueueOpen('search');

    $page->click(addQueueButton($id))->wait(1);

    expect(queueBrowserMessage($page))->toBe('The queue is full (500 songs). Remove some songs first.')
        ->and(PlaybackQueueItem::query()->where('playback_queue_id', $queue->getKey())->count())->toBe(500);
});
