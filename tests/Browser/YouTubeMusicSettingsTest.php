<?php

// youtube-music-connection, task 4.5: "The YouTube Music settings page uses the Aero look" in each state, the link
// bar back to Spotify, and the page following an approval by itself. Google is faked; no test reaches it.

use App\Models\User;
use App\Models\YouTubeMusicConnection;
use App\YouTubeMusic\ConnectionAttempts;
use App\YouTubeMusic\DeviceCode;
use App\YouTubeMusic\GoogleDeviceFlow;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    configureYouTubeMusic();

    // The attempt lives in the cache between requests, as in production (design D2).
    config(['cache.default' => 'database']);

    // A second Http::fake() would not replace these stubs, so a test changes the token answer through this property.
    $this->tokenAnswer = fn () => Http::response(['error' => 'authorization_pending'], 428);

    Http::preventStrayRequests();
    Http::fake([
        GoogleDeviceFlow::TOKEN_URL => fn () => ($this->tokenAnswer)(),
        GoogleDeviceFlow::CHANNELS_URL.'*' => Http::response(googleFixture('channels-owner')),
        GoogleDeviceFlow::REVOKE_URL => Http::response([]),
    ]);
});

/**
 * Signs the owner in, puts YouTube Music in the given state and opens its settings page.
 */
function ownerYouTubeMusicSettings(string $state)
{
    $page = signedInOwnerVisit('/dashboard');

    match ($state) {
        'not connected' => null,
        'waiting' => app(ConnectionAttempts::class)->start(
            new DeviceCode('dev-123', 'ABCD-EFGH', 'https://www.google.com/device', 5, 1800),
        ),
        'connected' => YouTubeMusicConnection::factory()->for(User::sole())->create(),
        'reconnect required' => YouTubeMusicConnection::factory()->for(User::sole())->reconnectRequired()->create(),
        'configuration error' => YouTubeMusicConnection::factory()->for(User::sole())->create(['config_error_at' => now()]),
    };

    return $page->navigate('/settings/youtube-music');
}

dataset('youtube music states', [
    'not connected' => ['not connected', 'Connect YouTube Music'],
    'waiting' => ['waiting', 'ABCD-EFGH'],
    'connected' => ['connected', 'YouTube Music connected'],
    'reconnect required' => ['reconnect required', 'Please reconnect YouTube Music.'],
    // youtube-music-credentials, task 3.2.
    'configuration error' => ['configuration error', 'YouTube Music is not configured correctly.'],
]);

test('The page is Aero in every state', function (string $state, string $text) {
    $page = ownerYouTubeMusicSettings($state)->assertSee($text);

    expect($page->script('document.querySelectorAll("[data-aero=background]").length'))->toBe(1)
        ->and($page->script('document.querySelectorAll("#youtube-music-settings [data-aero=glass-panel]").length'))->toBe(1)
        ->and($page->script('document.querySelectorAll("[data-slot]").length'))->toBe(0);
})->with('youtube music states');

test('The page shows keyboard focus', function (string $state, string $text) {
    $page = ownerYouTubeMusicSettings($state)->assertSee($text);

    // The walk starts on <main>, before the "Back" link.
    aeroAssertFocusWalk($page, '#youtube-music-settings');
})->with('youtube music states');

test('The page fits a 375 px phone', function (string $state, string $text) {
    $page = ownerYouTubeMusicSettings($state)->resize(375, 667)->assertSee($text);

    expect($page->script(AERO_MOBILE_PROBLEMS))->toBe([]);
})->with('youtube music states');

test('the waiting page shows the code, the address in a new tab and the waiting text', function () {
    $page = ownerYouTubeMusicSettings('waiting')
        ->assertSee('ABCD-EFGH')
        ->assertSee('Waiting for you to approve the code…');

    expect($page->script('document.querySelector("[data-part=verification-url]").getAttribute("href")'))->toBe('https://www.google.com/device')
        ->and($page->script('document.querySelector("[data-part=verification-url]").getAttribute("target")'))->toBe('_blank')
        ->and($page->script('document.querySelector("[data-part=verification-url]").getAttribute("rel")'))->toBe('noopener')
        ->and($page->script('document.body.innerHTML.includes("dev-123")'))->toBeFalse();
});

test('Going back to the Spotify settings', function () {
    $page = ownerYouTubeMusicSettings('not connected');

    expect($page->script('document.querySelector("[data-part=settings-nav] a[aria-current=page]").textContent.trim()'))->toBe('YouTube Music');

    $page->click('[data-part=settings-nav] a[href="/settings/spotify"]')
        ->wait(1)
        ->assertPathIs('/settings/spotify')
        ->assertSee('Connect Spotify');
});

test('the page follows the approval without a manual reload', function () {
    $this->tokenAnswer = fn () => Http::response(googleFixture('token'));

    ownerYouTubeMusicSettings('waiting')
        ->assertSee('ABCD-EFGH')
        ->wait(4)
        ->assertSee('YouTube Music connected')
        ->assertSee('Alice Music')
        ->assertDontSee('ABCD-EFGH');

    expect(YouTubeMusicConnection::count())->toBe(1);
});
