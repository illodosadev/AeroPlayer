<?php

// playback-queue, task 3.3: reading the queue, the JSON 401 of every queue endpoint, no-store, and the shared poll
// interval (spec "playback-queue"). No test reaches Spotify.

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('Reading a stored queue', function () {
    $owner = User::factory()->create();
    queueStored($owner, ['Song A', 'Song B', 'Song C'], current: 0, version: 3);

    $response = $this->actingAs($owner)->getJson('/queue')->assertOk();

    expect(array_keys($response->json()))->toBe(['version', 'current_item_id', 'shuffle', 'repeat', 'mode', 'context', 'has_more', 'items'])
        ->and($response->json('version'))->toBe(3)
        ->and($response->json('shuffle'))->toBeFalse()
        ->and($response->json('repeat'))->toBe('off')
        ->and($response->json('mode'))->toBe('queue')
        ->and($response->json('context'))->toBeNull()
        ->and($response->json('has_more'))->toBeFalse()
        ->and(array_column($response->json('items'), 'title'))->toBe(['Song A', 'Song B', 'Song C'])
        ->and($response->json('current_item_id'))->toBe(queueItemId($owner, 'Song A'));

    foreach ($response->json('items') as $item) {
        expect(array_keys($item))->toBe(QUEUE_ITEM_KEYS);
    }

    expect($response->json('items.0'))->toMatchArray([
        'source' => 'spotify',
        'id' => queueSongId('Song A'),
        'uri' => 'spotify:track:'.queueSongId('Song A'),
        'artists' => ['Song A Artist'],
        'album' => 'Song A Album',
        'duration_ms' => 200000,
        'image' => null,
        'origin' => null,
    ]);
});

test('Played songs stay in the queue', function () {
    $owner = User::factory()->create();
    queueStored($owner, ['Song C', 'Song A', 'Song D'], current: 1);

    $response = $this->actingAs($owner)->getJson('/queue')->assertOk();

    expect(array_column($response->json('items'), 'title'))->toBe(['Song C', 'Song A', 'Song D'])
        ->and($response->json('current_item_id'))->toBe(queueItemId($owner, 'Song A'));
});

test('No queue yet', function () {
    $response = $this->actingAs(User::factory()->create())->getJson('/queue')->assertOk();

    expect($response->json('items'))->toBe([])
        ->and($response->json('current_item_id'))->toBeNull()
        ->and($response->json('version'))->toBe(0);
});

test('A visitor without a session', function (string $method, string $uri) {
    $owner = User::factory()->create();
    queueStored($owner, ['Song A'], version: 2);

    $this->json($method, $uri, ['version' => 2, 'tracks' => [queueSong('Song X')], 'position' => 0, 'current_item_id' => 1])
        ->assertStatus(401)
        ->assertExactJson(['error' => 'unauthenticated']);

    expect(queueTitles($owner))->toBe(['Song A']);
    Http::assertNothingSent();
})->with([
    'GET /queue' => ['GET', '/queue'],
    'PUT /queue' => ['PUT', '/queue'],
    'PATCH /queue' => ['PATCH', '/queue'],
    'POST /queue/items' => ['POST', '/queue/items'],
    'PATCH /queue/items/{item}' => ['PATCH', '/queue/items/1'],
    'DELETE /queue/items/{item}' => ['DELETE', '/queue/items/1?version=2'],
]);

test('Queue answers are not cached', function () {
    $response = $this->actingAs(User::factory()->create())->getJson('/queue');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('a queue write with an expired session answers the JSON 419', function () {
    // Laravel skips CSRF checks in tests; re-enable them for this test only, as the logout test does.
    $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });

    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->postJson('/queue/items', ['version' => 0, 'tracks' => [queueSong('Song X')]])
        ->assertStatus(419)
        ->assertExactJson(['error' => 'session_expired']);

    expect(queueTitles($owner))->toBe([]);
});

test('the shared poll interval is 30 seconds by default', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('player.queuePollSeconds', 30));
});

test('guests get no poll interval', function () {
    $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('player', null));
});
