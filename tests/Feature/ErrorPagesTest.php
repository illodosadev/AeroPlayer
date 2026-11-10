<?php

// Error responses leak no internal details (openspec change project-foundation, capability error-pages).

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/**
 * Asserts that the response carries no server file path, stack trace or given secret.
 */
function assertNoInternalDetails(TestResponse $response, string ...$secrets): void
{
    $body = $response->getContent();

    foreach ([base_path(), storage_path(), 'Stack trace', '#0 ', ...$secrets] as $forbidden) {
        expect($body)->not->toContain($forbidden);
    }
}

beforeEach(function () {
    config(['app.debug' => false]);
});

test('an unknown route returns 404 without internal details', function () {
    $response = $this->get('/route-that-does-not-exist');

    $response->assertNotFound();
    assertNoInternalDetails($response);
});

test('an uncaught exception returns 500 without internal details', function () {
    Route::get('/__test/boom', fn () => throw new RuntimeException('boom-secret-1234'));

    $response = $this->get('/__test/boom');

    $response->assertStatus(500);
    assertNoInternalDetails($response, 'boom-secret-1234');
});

test('an unreachable database returns 500 without credentials or host details', function () {
    // The driver is needed to reach a real connection failure, not a missing extension.
    expect(extension_loaded('pdo_mysql'))->toBeTrue();

    // Only the session uses the unreachable connection; database.default stays on SQLite so
    // RefreshDatabase can roll back cleanly at teardown.
    config([
        'database.connections.unreachable' => [
            'driver' => 'mysql',
            'host' => 'db.invalid',
            'port' => '3306',
            'database' => 'aeroplayer',
            'username' => 'aeroplayer',
            'password' => 'db-secret-5678',
            'options' => [PDO::ATTR_TIMEOUT => 1],
        ],
        'session.driver' => 'database',
        'session.connection' => 'unreachable',
    ]);
    app()->forgetInstance('session');
    app()->forgetInstance('session.store');

    try {
        // Proves the failure is a real connection error, not a configuration one.
        expect(fn () => DB::connection('unreachable')->getPdo())->toThrow(PDOException::class);

        $start = microtime(true);
        $response = $this->get('/login');
        $elapsed = microtime(true) - $start;

        $response->assertStatus(500);
        assertNoInternalDetails($response, 'db-secret-5678', 'db.invalid');
        expect($elapsed)->toBeLessThan(3.0);
    } finally {
        DB::purge('unreachable');
    }
});
