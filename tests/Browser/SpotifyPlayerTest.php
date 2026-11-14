<?php

// spotify-web-player: the browser player with the fake SDK of tests/Browser/fixtures (design D12). The fake is served
// by a route that exists only in this test's application, and api.spotify.com is faked on the server side. Helper
// names carry a "player" prefix because Pest declares them globally.

use App\Models\SpotifyConnection;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

const PLAYER_FAKE_SDK_PATH = '/testing/fake-spotify-sdk.js';

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(['api.spotify.com/*' => Http::response(null, 204)]);

    Route::get(PLAYER_FAKE_SDK_PATH, fn () => response()->file(
        base_path('tests/Browser/fixtures/fake-spotify-sdk.js'),
        ['Content-Type' => 'application/javascript'],
    ));

    config()->set('app.name', 'Music');
    config()->set('services.spotify', [
        'client_id' => 'client-abc',
        'client_secret' => 'secret-xyz',
        'owner_id' => 'owner-123',
        'redirect_uri' => 'http://127.0.0.1:8000/spotify/callback',
        'timeout' => 10,
        'sdk_url' => PLAYER_FAKE_SDK_PATH,
    ]);
});

/**
 * Sign the owner in, with a Spotify connection unless $connected is false, and open the given page. The remembered
 * volume is cleared before signing in, so no other test's volume leaks in.
 */
function playerOwnerAt(string $path, bool $connected = true, array $attributes = [])
{
    $owner = User::factory()->create(['name' => 'Owner Person', 'email' => 'owner@example.com', 'password' => 'Correct-Horse-42']);

    if ($connected) {
        SpotifyConnection::factory()->for($owner)->create(['access_token_expires_at' => now()->addHour(), ...$attributes]);
    }

    $page = visit('/login');
    $page->script('localStorage.removeItem("aeroplayer.volume")');

    return $page->fill('email', 'owner@example.com')
        ->fill('password', 'Correct-Horse-42')
        ->press('Log in')
        ->wait(2)
        ->navigate($path)
        ->wait(1);
}

test('the fake SDK loads and defines Spotify.Player', function () {
    $page = visit('/login');

    $page->script('(() => {
        const script = document.createElement("script");
        script.src = "'.PLAYER_FAKE_SDK_PATH.'";
        document.head.appendChild(script);
    })()');

    $page->wait(1);

    expect($page->script('typeof window.Spotify?.Player'))->toBe('function')
        ->and($page->script('Array.isArray(window.__fakeSpotify?.calls)'))->toBeTrue();
});

// --- The browser player is created only with Spotify connected (task 2.1) ----------------------------------------

test('The player bar is on every signed-in page', function () {
    $page = playerOwnerAt('/dashboard', connected: false);

    foreach (['/dashboard', '/styleguide', '/settings/spotify'] as $path) {
        $page->navigate($path)->wait(1);

        expect($page->script('document.querySelectorAll("#app-player [data-aero=player-bar]").length'))->toBe(1);
    }

    $page->script('fetch("/logout", {method: "POST", headers: {"X-XSRF-TOKEN": decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)[1])}})');
    $page->wait(1)->navigate('/login')->wait(1);

    expect($page->script('document.querySelectorAll("#app-player").length'))->toBe(0);
});

test('Spotify not connected', function () {
    $page = playerOwnerAt('/dashboard', connected: false);

    expect($page->script('document.querySelectorAll("script[data-spotify-sdk]").length'))->toBe(0)
        ->and($page->script('typeof window.__fakeSpotify'))->toBe('undefined')
        ->and($page->script('document.querySelector("#app-player [data-part=player-notice]")?.textContent.trim()'))
        ->toBe('Connect Spotify to play your music.')
        ->and($page->script('document.querySelector("#app-player a[href]")?.getAttribute("href")'))
        ->toContain('/settings/spotify');
});

// --- The SDK device, its token, readiness, the bar's state and the progress (task 2.2) ---------------------------

/**
 * Emit an SDK event in the page.
 */
function playerEmit($page, string $event, string $payload = '{}'): void
{
    $page->script('window.__fakeSpotify.emit("'.$event.'", '.$payload.')');
}

/**
 * A "player_state_changed" payload, stamped with the page's current time.
 */
function playerState(string $title = 'Track A', int $position = 10000, bool $paused = false, int $nextTracks = 1, int $duration = 180000): string
{
    $next = json_encode(array_fill(0, $nextTracks, ['uri' => 'spotify:track:next', 'name' => 'Next']));

    return '{
        position: '.$position.', duration: '.$duration.', paused: '.($paused ? 'true' : 'false').', timestamp: Date.now(),
        track_window: {
            current_track: {
                uri: "spotify:track:a", name: "'.$title.'", duration_ms: '.$duration.',
                artists: [{name: "Artist 1"}, {name: "Artist 2"}],
                album: {name: "Album", images: [{url: "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=="}]}
            },
            next_tracks: '.$next.'
        }
    }';
}

/**
 * The owner on the dashboard with the fake SDK ready as device "web-1".
 */
function playerReady()
{
    $page = playerOwnerAt('/dashboard');
    playerEmit($page, 'ready', '{device_id: "web-1"}');

    return $page->wait(0.5);
}

function playerText($page, string $part): ?string
{
    return $page->script('document.querySelector("#app-player [data-part='.$part.'], #app-player [data-time='.$part.']")?.textContent.trim() ?? null');
}

test('The SDK player is created with Spotify connected', function () {
    $page = playerOwnerAt('/dashboard');

    expect($page->script('window.__fakeSpotify.players'))->toBe([['name' => 'Music', 'volume' => 0.5]])
        ->and($page->script('window.__fakeSpotify.callNames()'))->toContain('connect');
});

test('The SDK asks the backend for a token', function () {
    $page = playerOwnerAt('/dashboard', attributes: ['access_token' => 'tok-456']);

    expect($page->script('window.__fakeSpotify.requestToken()'))->toBe('tok-456');
});

test('Controls disabled until the device is ready', function () {
    $page = playerOwnerAt('/dashboard');
    $disabled = fn (string $label) => $page->script('document.querySelector("#app-player button[aria-label='.$label.']").disabled');

    expect($disabled('Play'))->toBeTrue()
        ->and($disabled('Previous'))->toBeTrue()
        ->and($disabled('Next'))->toBeTrue();

    playerEmit($page, 'ready', '{device_id: "web-1"}');
    $page->wait(0.5);

    expect($disabled('Play'))->toBeFalse();
});

test('The bar shows the current track', function () {
    $page = playerReady();
    playerEmit($page, 'player_state_changed', playerState());
    $page->wait(0.5);

    expect(playerText($page, 'title'))->toBe('Track A')
        ->and(playerText($page, 'artist'))->toBe('Artist 1, Artist 2')
        ->and($page->script('document.querySelector("#app-player img")?.getAttribute("src")'))->toBe('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
        ->and(playerText($page, 'elapsed'))->toBe('0:10')
        ->and(playerText($page, 'total'))->toBe('3:00')
        ->and($page->script('document.querySelectorAll("#app-player button[aria-label=Pause]").length'))->toBe(1);
});

test('No next track', function () {
    $page = playerReady();
    playerEmit($page, 'player_state_changed', playerState('Track C', nextTracks: 0));
    $page->wait(0.5);

    expect($page->script('document.querySelector("#app-player button[aria-label=Next]").disabled'))->toBeTrue();

    $page->script('document.querySelector("#app-player button[aria-label=Next]").click()');

    expect($page->script('window.__fakeSpotify.callNames()'))->not->toContain('nextTrack');
});

test('The progress advances while playing', function () {
    $page = playerReady();
    playerEmit($page, 'player_state_changed', playerState(position: 10000));
    $page->wait(2);

    expect(playerText($page, 'elapsed'))->toBeIn(['0:11', '0:12', '0:13']);
});

test('The progress stops while paused', function () {
    $page = playerReady();
    playerEmit($page, 'player_state_changed', playerState(position: 10000, paused: true));
    $page->wait(2);

    expect(playerText($page, 'elapsed'))->toBe('0:10')
        ->and($page->script('document.querySelectorAll("#app-player button[aria-label=Play]").length'))->toBe(1);
});

// --- The bar controls, blocked autoplay and the remembered volume (task 2.3) -------------------------------------

function playerPress($page, string $label): void
{
    $page->script('document.querySelector("#app-player button[aria-label='.$label.']").click()');
    $page->wait(0.3);
}

function playerSlide($page, string $label, int $value): void
{
    $page->script('(() => {
        const input = document.querySelector("#app-player input[aria-label='.$label.']");
        input.value = "'.$value.'";
        input.dispatchEvent(new Event("input", {bubbles: true}));
    })()');
    $page->wait(0.3);
}

/**
 * The arguments of every recorded call to one SDK method.
 */
function playerCallArgs($page, string $method): array
{
    return $page->script('window.__fakeSpotify.calls.filter((call) => call.name === "'.$method.'").map((call) => call.args)');
}

test('Pause and resume', function () {
    $page = playerReady();
    playerEmit($page, 'player_state_changed', playerState());
    $page->wait(0.3);

    playerPress($page, 'Pause');
    expect($page->script('window.__fakeSpotify.callNames()'))->toContain('pause');

    playerEmit($page, 'player_state_changed', playerState(paused: true));
    $page->wait(0.3);
    playerPress($page, 'Play');

    expect($page->script('window.__fakeSpotify.callNames()'))->toContain('resume');
});

test('Change the volume', function () {
    $page = playerReady();
    playerEmit($page, 'player_state_changed', playerState());
    playerSlide($page, 'Volume', 30);

    expect(playerCallArgs($page, 'setVolume'))->toBe([[0.3]])
        ->and($page->script('document.querySelector("#app-player input[aria-label=Volume]").getAttribute("aria-valuetext")'))->toBe('30%');
});

test('Seek within the track', function () {
    $page = playerReady();
    playerEmit($page, 'player_state_changed', playerState(duration: 180000));
    playerSlide($page, 'Seek', 90);

    expect(playerCallArgs($page, 'seek'))->toBe([[90000]])
        ->and(playerText($page, 'elapsed'))->toBeIn(['1:30', '1:31']);
});

test('Next and previous within the context', function () {
    $page = playerReady();
    playerEmit($page, 'player_state_changed', playerState(position: 1000, nextTracks: 2));
    $page->wait(0.3);

    playerPress($page, 'Next');
    expect($page->script('window.__fakeSpotify.callNames()'))->toContain('nextTrack');

    playerPress($page, 'Previous');
    expect($page->script('window.__fakeSpotify.callNames()'))->toContain('previousTrack');
});

test('Previous restarts a track after 3 seconds', function () {
    $page = playerReady();
    playerEmit($page, 'player_state_changed', playerState(position: 10000, paused: true));
    $page->wait(0.3);

    playerPress($page, 'Previous');

    expect(playerCallArgs($page, 'seek'))->toBe([[0]])
        ->and($page->script('window.__fakeSpotify.callNames()'))->not->toContain('previousTrack')
        ->and(playerText($page, 'elapsed'))->toBe('0:00');
});

test('The browser blocks autoplay', function () {
    $page = playerReady();
    playerEmit($page, 'autoplay_failed');
    $page->wait(0.3);

    expect(playerText($page, 'player-notice'))->toBe('Press play to start listening');

    playerPress($page, 'Play');

    $names = $page->script('window.__fakeSpotify.callNames()');
    $activate = array_search('activateElement', $names, true);
    $resume = array_search('resume', $names, true);

    expect($activate)->not->toBeFalse()
        ->and($resume)->not->toBeFalse()
        ->and($activate)->toBeLessThan($resume);
});

test('The volume survives a reload', function () {
    $page = playerReady();
    playerSlide($page, 'Volume', 30);

    $page->navigate('/dashboard')->wait(1);

    expect($page->script('window.__fakeSpotify.players'))->toBe([['name' => 'Music', 'volume' => 0.3]])
        ->and($page->script('document.querySelector("#app-player input[aria-label=Volume]").getAttribute("aria-valuetext")'))->toBe('30%');
});

// --- Features start playback through the player function (task 2.4) ----------------------------------------------

/**
 * Record each fetch in the fake SDK's call log, so the order against activateElement() can be checked.
 */
function playerRecordFetches($page): void
{
    $page->script('(() => {
        const original = window.fetch;
        window.fetch = (input, init) => {
            window.__fakeSpotify.calls.push({name: "fetch " + String(input), args: []});
            return original(input, init);
        };
    })()');
}

test('Starting playback from the player function', function (string $arguments, array $body) {
    $page = playerReady();
    playerRecordFetches($page);

    expect($page->script('window.aeroPlayer.play('.$arguments.').then(() => "ok", (error) => error.message)'))->toBe('ok');

    $names = $page->script('window.__fakeSpotify.callNames()');

    expect(array_search('activateElement', $names, true))
        ->toBeLessThan(array_search('fetch /spotify/player/play', $names, true));

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && $request->url() === 'https://api.spotify.com/v1/me/player/play?device_id=web-1'
        && $request->data() == $body);
})->with([
    'a context from position 0' => [
        '{contextUri: "spotify:album:abc", offset: {position: 0}}',
        ['context_uri' => 'spotify:album:abc', 'offset' => ['position' => 0]],
    ],
    'a context from a track' => [
        '{contextUri: "spotify:album:abc", offset: {uri: "spotify:track:t2"}}',
        ['context_uri' => 'spotify:album:abc', 'offset' => ['uri' => 'spotify:track:t2']],
    ],
    'one track' => [
        '{uris: ["spotify:track:t1"]}',
        ['uris' => ['spotify:track:t1'], 'offset' => ['position' => 0]],
    ],
]);

test('The player function rejects invalid arguments', function () {
    $page = playerReady();
    playerRecordFetches($page);

    $neither = $page->script('window.aeroPlayer.play({}).then(() => "ok", (error) => error.message)');
    $both = $page->script('window.aeroPlayer.play({contextUri: "spotify:album:abc", uris: ["spotify:track:t1"]}).then(() => "ok", (error) => error.message)');

    expect($neither)->toBe('play() needs exactly one of contextUri or uris')
        ->and($both)->toBe('play() needs exactly one of contextUri or uris')
        ->and($page->script('window.__fakeSpotify.callNames()'))->not->toContain('fetch /spotify/player/play');

    Http::assertNothingSent();
});

// --- Playback survives navigation, and leaving stops the player (task 2.5) ---------------------------------------

test('The music keeps playing across pages', function () {
    $page = playerOwnerAt('/settings/spotify');
    playerEmit($page, 'ready', '{device_id: "web-1"}');
    playerEmit($page, 'player_state_changed', playerState('Track A'));

    // "Back" is an Inertia link: the visit keeps the persistent layout.
    $page->click('.spotify-settings__back')->wait(1)->assertPathIs('/dashboard');

    expect($page->script('window.__fakeSpotify.players.length'))->toBe(1)
        ->and($page->script('window.__fakeSpotify.callNames()'))->not->toContain('disconnect')
        ->and(playerText($page, 'title'))->toBe('Track A');
});

test('Logging out stops the music', function () {
    $page = playerReady();
    playerEmit($page, 'player_state_changed', playerState());

    $page->click('Owner Person')->wait(1)->click('Log out')->wait(2)->assertPathIs('/login');

    expect($page->script('window.__fakeSpotify?.callNames() ?? []'))->toContain('disconnect');
});

test('Disconnecting Spotify with music playing', function () {
    $page = playerOwnerAt('/settings/spotify');
    playerEmit($page, 'ready', '{device_id: "web-1"}');
    playerEmit($page, 'player_state_changed', playerState());

    $page->click('#spotify-disconnect')->wait(2)->assertSee('Spotify disconnected.');

    expect($page->script('window.__fakeSpotify.callNames()'))->toContain('disconnect')
        ->and(playerText($page, 'player-notice'))->toBe('Connect Spotify to play your music.');
});

test('An expired session sends the owner to sign in', function () {
    $page = playerReady();

    // End the session in the background, then let the SDK ask for a token: the 401 sends the owner to sign in.
    $page->script('fetch("/logout", {method: "POST", headers: {"X-XSRF-TOKEN": decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)[1])}})');
    $page->wait(1);
    $page->script('window.__fakeSpotify.requestToken(); true');

    $page->wait(2)->assertPathIs('/login');
});
