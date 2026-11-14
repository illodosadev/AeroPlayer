<?php

// spotify-connection, tasks 1.2 and 1.3: "Spotify tokens are encrypted at rest" and "There is at most one
// Spotify connection", on the repository alone.

use App\Enums\SpotifyConnectionStatus;
use App\Models\SpotifyConnection;
use App\Models\User;
use App\Repositories\SpotifyConnectionRepository;
use App\Spotify\SpotifyAccount;
use App\Spotify\SpotifyScopes;
use App\Spotify\SpotifyTokens;
use Illuminate\Support\Facades\DB;

function spotifyTokens(string $refresh = 'AQ-refresh-456', string $access = 'BQ-access-123'): SpotifyTokens
{
    return new SpotifyTokens($access, $refresh, 3600, implode(' ', SpotifyScopes::REQUIRED));
}

test('Tokens are stored encrypted', function () {
    $owner = User::factory()->create();

    app(SpotifyConnectionRepository::class)
        ->storeForOwner($owner->id, new SpotifyAccount('owner-123', 'Alice', null), spotifyTokens());

    $raw = (array) DB::table('spotify_connections')->first();

    foreach ($raw as $value) {
        expect((string) $value)->not->toContain('BQ-access-123')->not->toContain('AQ-refresh-456');
    }

    $connection = app(SpotifyConnectionRepository::class)->current();

    expect($connection->access_token)->toBe('BQ-access-123')
        ->and($connection->refresh_token)->toBe('AQ-refresh-456')
        ->and($connection->toArray())->not->toHaveKeys(['access_token', 'refresh_token']);
});

test('storing records the account, the scopes, the expiry and the authorization date', function () {
    $this->freezeSecond();
    $owner = User::factory()->create();

    $connection = app(SpotifyConnectionRepository::class)
        ->storeForOwner($owner->id, new SpotifyAccount('owner-123', 'Alice', null), spotifyTokens());

    expect($connection->spotify_user_id)->toBe('owner-123')
        ->and($connection->display_name)->toBe('Alice')
        ->and($connection->scopes)->toBe(implode(' ', SpotifyScopes::REQUIRED))
        ->and($connection->access_token_expires_at->equalTo(now()->addSeconds(3600)))->toBeTrue()
        ->and($connection->authorized_at->equalTo(now()))->toBeTrue()
        ->and($connection->status)->toBe(SpotifyConnectionStatus::Connected);
});

test('storing twice keeps one row, replaces the tokens and gives a new connection identifier', function () {
    $repository = app(SpotifyConnectionRepository::class);
    $owner = User::factory()->create();
    $account = new SpotifyAccount('owner-123', 'Alice', null);

    $first = $repository->storeForOwner($owner->id, $account, spotifyTokens());
    $second = $repository->storeForOwner($owner->id, $account, spotifyTokens('AQ-new-777', 'BQ-new-888'));

    expect(SpotifyConnection::count())->toBe(1)
        ->and($repository->current()->refresh_token)->toBe('AQ-new-777')
        ->and($repository->current()->access_token)->toBe('BQ-new-888')
        ->and($second->connection_id)->not->toBe($first->connection_id);
});

test('storing after reconnect_required sets the status back to connected', function () {
    $repository = app(SpotifyConnectionRepository::class);
    $existing = SpotifyConnection::factory()->reconnectRequired()->create();

    expect($repository->status())->toBe(SpotifyConnectionStatus::ReconnectRequired);

    $repository->storeForOwner($existing->user_id, new SpotifyAccount('owner-123', 'Alice', null), spotifyTokens());

    expect($repository->status())->toBe(SpotifyConnectionStatus::Connected)
        ->and($repository->current()->refresh_token)->toBe('AQ-refresh-456');
});

test('status is null and current is null when Spotify is not connected', function () {
    $repository = app(SpotifyConnectionRepository::class);

    expect($repository->status())->toBeNull()
        ->and($repository->current())->toBeNull();
});

test('deleting removes the connection, and deleting again does nothing', function () {
    $repository = app(SpotifyConnectionRepository::class);
    $connection = SpotifyConnection::factory()->create();

    $repository->deleteForOwner($connection->user_id);
    $repository->deleteForOwner($connection->user_id);

    expect(SpotifyConnection::count())->toBe(0);
});

test('deleting the owner account deletes its connection', function () {
    $connection = SpotifyConnection::factory()->create();

    $connection->user->delete();

    expect(SpotifyConnection::count())->toBe(0);
});
