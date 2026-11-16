<?php

// playback-queue, tasks 4.2 and 4.3: the queue panel of the player bar, its rows, and reordering and removing songs
// (spec "playback-queue"). The browser server runs in the test process, so the queue is stored and checked in the
// test database. No test reaches Spotify.

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('Seeing the queue', function () {
    $owner = queueBrowserOwner();
    queueStored($owner, ['Song C', 'Song A', 'Song B', 'Song D', 'Song E'], current: 1);

    $page = queueBrowserVisit();

    expect($page->script('document.querySelector("[data-part=queue-toggle]").getAttribute("aria-expanded")'))->toBe('false');

    queueBrowserOpen($page);

    expect(queueBrowserTitles($page, 'now-playing'))->toBe(['Song A'])
        ->and(queueBrowserTitles($page, 'next-up'))->toBe(['Song B', 'Song D', 'Song E'])
        ->and($page->script('document.querySelector("#queue-panel").textContent.includes("Song C")'))->toBeFalse()
        ->and($page->script('document.querySelector("[data-part=queue-toggle]").getAttribute("aria-expanded")'))->toBe('true')
        ->and($page->script('document.querySelector("[data-part=queue-toggle]").getAttribute("aria-controls")'))->toBe('queue-panel');

    $row = $page->script('(() => {
        const row = document.querySelector("#queue-panel [data-section=next-up] [data-part=queue-row]");
        return ["title", "artists", "duration", "source"].map((part) => row.querySelector(`[data-part=${part}]`).textContent.trim());
    })()');

    expect($row)->toBe(['Song B', 'Song B Artist', '3:20', 'Spotify']);
    $page->assertSee('Now playing')->assertSee('Next up');
});

test('Empty queue', function () {
    queueBrowserOwner();

    $page = queueBrowserOpen(queueBrowserVisit());

    $page->assertSee('Your queue is empty.');
    expect($page->script('document.querySelectorAll("#queue-panel [data-part=queue-row]").length'))->toBe(0);
});

test('Closing the panel with Escape', function () {
    $owner = queueBrowserOwner();
    queueStored($owner, ['Song A', 'Song B']);

    $page = queueBrowserOpen(queueBrowserVisit());

    // Opening moves the focus to the panel's heading.
    expect($page->script('document.activeElement?.id'))->toBe('queue-panel-title');

    $page->keys('#queue-panel-title', ['Escape'])->wait(0.5);

    expect($page->script('document.querySelectorAll("#queue-panel").length'))->toBe(0)
        ->and($page->script('document.querySelector("[data-part=queue-toggle]").getAttribute("aria-expanded")'))->toBe('false')
        ->and($page->script('document.activeElement?.dataset.part'))->toBe('queue-toggle');
});

test('The panel on a phone', function () {
    $owner = queueBrowserOwner();
    queueStored($owner, ['Song A', 'Song B with a rather long title that has to be cut', 'Song C', 'Song D']);

    // An Aero page: the kit's dashboard still has its own small sidebar toggle until T-25.
    $page = queueBrowserVisit('/search');
    $page->resize(375, 667)->wait(1);
    queueBrowserOpen($page);

    expect($page->script(AERO_MOBILE_PROBLEMS))->toBe([])
        ->and($page->script('(() => {
            const box = document.querySelector("#queue-panel").getBoundingClientRect();
            return box.left >= 0 && box.right <= window.innerWidth && box.top >= 0;
        })()'))->toBeTrue()
        ->and($page->script('[...document.querySelectorAll("#queue-panel button")].filter((b) => {
            const r = b.getBoundingClientRect();
            return r.width < 44 || r.height < 44;
        }).length'))->toBe(0);
});

test('Spotify not connected', function () {
    $owner = queueBrowserOwner(connected: false);
    queueStored($owner, ['Song A', 'Song B', 'Song C']);

    $page = queueBrowserOpen(queueBrowserVisit());

    expect(queueBrowserTitles($page, 'now-playing'))->toBe(['Song A'])
        ->and(queueBrowserTitles($page, 'next-up'))->toBe(['Song B', 'Song C'])
        ->and($page->script('document.querySelector("#app-player [data-part=player-notice]")?.textContent.trim()'))
        ->toBe('Connect Spotify to play your music.')
        ->and($page->script('document.querySelector("#app-player [aria-label=Play], #app-player [aria-label=Pause]").disabled'))->toBeTrue();

    $page->click('#queue-panel [data-section=next-up] [data-part=queue-row]:last-child [data-action=move-up]')->wait(1);
    expect(queueTitles($owner))->toBe(['Song A', 'Song C', 'Song B']);

    $page->click('#queue-panel [data-section=next-up] [data-part=queue-row]:last-child [data-action=remove]')->wait(1);
    expect(queueTitles($owner))->toBe(['Song A', 'Song C']);
});

test('the Queue button keeps the bar\'s touch area', function () {
    queueBrowserOwner();

    $page = queueBrowserVisit();

    expect($page->script('(() => {
        const r = document.querySelector("[data-part=queue-toggle]").getBoundingClientRect();
        return r.width >= 44 && r.height >= 44;
    })()'))->toBeTrue()
        ->and($page->script('document.querySelector("[data-part=queue-toggle]").closest("[data-aero=player-bar]") !== null'))->toBeTrue();
});

// --- Reordering and removing (task 4.3) ----------------------------------------------------------------------------

test('Reordering and removing songs', function () {
    $owner = queueBrowserOwner();
    queueStored($owner, ['Song N', 'Song A', 'Song B', 'Song C']);

    $page = queueBrowserOpen(queueBrowserVisit());

    $c = '#queue-panel [data-item-id="'.queueItemId($owner, 'Song C').'"]';
    $a = '#queue-panel [data-item-id="'.queueItemId($owner, 'Song A').'"]';
    $page->drag($c, $a)->wait(1);

    expect(queueBrowserTitles($page, 'next-up'))->toBe(['Song C', 'Song A', 'Song B']);

    $page->click('#queue-panel [data-item-id="'.queueItemId($owner, 'Song B').'"] [data-action=remove]')->wait(1);

    expect(queueBrowserTitles($page, 'next-up'))->toBe(['Song C', 'Song A']);

    $page->navigate('/dashboard')->wait(1);
    $page->script('location.reload()');
    $page->wait(2);
    queueBrowserOpen($page);

    expect(queueBrowserTitles($page, 'next-up'))->toBe(['Song C', 'Song A'])
        ->and(queueTitles($owner))->toBe(['Song N', 'Song C', 'Song A']);
});

test('Reordering with the keyboard', function () {
    $owner = queueBrowserOwner();
    queueStored($owner, ['Song N', 'Song A', 'Song B', 'Song C']);

    $page = queueBrowserOpen(queueBrowserVisit());

    $moveUp = '#queue-panel [data-item-id="'.queueItemId($owner, 'Song C').'"] [data-action=move-up]';
    $page->script('document.querySelector(\''.$moveUp.'\').focus()');

    $page->keys($moveUp, ['Enter'])->wait(1);

    expect(queueBrowserTitles($page, 'next-up'))->toBe(['Song A', 'Song C', 'Song B'])
        ->and($page->script('document.activeElement?.matches(\''.$moveUp.'\')'))->toBeTrue();

    $page->keys($moveUp, ['Enter'])->wait(1);

    // At the top "Move up" is disabled, so the focus goes to the same row's "Move down".
    expect(queueBrowserTitles($page, 'next-up'))->toBe(['Song C', 'Song A', 'Song B'])
        ->and($page->script('document.activeElement?.closest("[data-item-id]")?.dataset.itemId'))->toBe((string) queueItemId($owner, 'Song C'))
        ->and($page->script('document.activeElement?.dataset.action'))->toBe('move-down');
});

test('The ends of the list', function () {
    $owner = queueBrowserOwner();
    queueStored($owner, ['Song N', 'Song A', 'Song B', 'Song C']);

    $page = queueBrowserOpen(queueBrowserVisit());

    $state = fn (string $title, string $action) => $page->script(
        'document.querySelector(\'#queue-panel [data-item-id="'.queueItemId($owner, $title).'"] [data-action='.$action.']\').disabled'
    );

    expect($state('Song A', 'move-up'))->toBeTrue()
        ->and($state('Song A', 'move-down'))->toBeFalse()
        ->and($state('Song B', 'move-up'))->toBeFalse()
        ->and($state('Song C', 'move-down'))->toBeTrue()
        ->and($page->script('document.querySelectorAll("#queue-panel [data-section=now-playing] [data-action]").length'))->toBe(1)
        ->and($page->script('document.querySelector("#queue-panel [data-section=now-playing] [data-action]").dataset.action'))->toBe('remove');
});

test('Reordering while a song plays', function () {
    $owner = queueBrowserOwner(player: true);
    queueStored($owner, ['Song N', 'Song B', 'Song C']);

    $page = queueBrowserVisit();
    $page->script('window.__fakeSpotify.emit("ready", {device_id: "web-1"})');
    $page->script('window.__fakeSpotify.emit("player_state_changed", {
        position: 10000, duration: 180000, paused: false, timestamp: Date.now(),
        track_window: { current_track: { uri: "spotify:track:a", name: "Track A", duration_ms: 180000,
            artists: [{name: "Artist 1"}], album: {name: "Album", images: []} }, next_tracks: [] }
    })');
    $page->wait(0.5);
    queueBrowserOpen($page);

    $page->click('#queue-panel [data-item-id="'.queueItemId($owner, 'Song C').'"] [data-action=move-up]')->wait(1);
    $page->click('#queue-panel [data-item-id="'.queueItemId($owner, 'Song B').'"] [data-action=remove]')->wait(1);

    expect(queueTitles($owner))->toBe(['Song N', 'Song C'])
        ->and($page->script('window.__fakeSpotify.callNames().filter((name) => ["pause", "resume", "togglePlay"].includes(name))'))->toBe([])
        ->and($page->script('document.querySelector("#app-player [data-part=title]")?.textContent.trim()'))->toBe('Track A')
        ->and($page->script('document.querySelectorAll("#app-player [aria-label=Pause]").length'))->toBe(1);

    expect(Http::recorded()->filter(fn (array $pair) => str_contains($pair[0]->url(), '/v1/me/player/play')))->toHaveCount(0);
});
