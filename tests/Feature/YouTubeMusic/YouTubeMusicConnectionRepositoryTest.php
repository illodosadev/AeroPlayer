<?php

// youtube-music-connection, tasks 1.2 and 1.3: "YouTube Music tokens are encrypted at rest" and "There is at most
// one YouTube Music connection", on the repository alone.

use App\Enums\YouTubeMusicConnectionStatus;
use App\Models\User;
use App\Models\YouTubeMusicConnection;
use App\Repositories\YouTubeMusicConnectionRepository;
use App\YouTubeMusic\GoogleDeviceFlow;
use App\YouTubeMusic\GoogleTokens;
use App\YouTubeMusic\YouTubeChannel;
use Illuminate\Support\Facades\DB;

function googleTokens(string $refresh = '1//refresh-456', string $access = 'ya29.access-123'): GoogleTokens
{
    return new GoogleTokens($access, $refresh, 3599, GoogleDeviceFlow::SCOPE);
}

function ownerChannel(): YouTubeChannel
{
    return new YouTubeChannel('UC-owner', 'Alice Music');
}

test('Tokens are stored encrypted', function () {
    $owner = User::factory()->create();

    app(YouTubeMusicConnectionRepository::class)->storeForOwner($owner->id, ownerChannel(), googleTokens());

    $raw = (array) DB::table('youtube_music_connections')->first();

    foreach ($raw as $value) {
        expect((string) $value)->not->toContain('ya29.access-123')->not->toContain('1//refresh-456');
    }

    $connection = app(YouTubeMusicConnectionRepository::class)->current();

    expect($connection->access_token)->toBe('ya29.access-123')
        ->and($connection->refresh_token)->toBe('1//refresh-456')
        ->and($connection->toArray())->not->toHaveKeys(['access_token', 'refresh_token']);
});

test('storing records the channel, the scopes, the expiry and the authorization date', function () {
    $this->freezeSecond();
    $owner = User::factory()->create();

    $connection = app(YouTubeMusicConnectionRepository::class)->storeForOwner($owner->id, ownerChannel(), googleTokens());

    expect($connection->channel_id)->toBe('UC-owner')
        ->and($connection->channel_title)->toBe('Alice Music')
        ->and($connection->scopes)->toBe(GoogleDeviceFlow::SCOPE)
        ->and($connection->access_token_expires_at->equalTo(now()->addSeconds(3599)))->toBeTrue()
        ->and($connection->authorized_at->equalTo(now()))->toBeTrue()
        ->and($connection->status)->toBe(YouTubeMusicConnectionStatus::Connected);
});

test('Reconnecting replaces the connection', function () {
    $repository = app(YouTubeMusicConnectionRepository::class);
    $existing = YouTubeMusicConnection::factory()->reconnectRequired()->create();

    expect($repository->status())->toBe(YouTubeMusicConnectionStatus::ReconnectRequired);

    $repository->storeForOwner($existing->user_id, ownerChannel(), googleTokens('1//refresh-777'));

    expect(YouTubeMusicConnection::count())->toBe(1)
        ->and($repository->status())->toBe(YouTubeMusicConnectionStatus::Connected)
        ->and($repository->current()->refresh_token)->toBe('1//refresh-777')
        ->and($repository->current()->channel_id)->toBe('UC-owner')
        ->and($repository->current()->connection_id)->not->toBe($existing->connection_id);
});

test('status and current are null when YouTube Music is not connected', function () {
    $repository = app(YouTubeMusicConnectionRepository::class);

    expect($repository->status())->toBeNull()
        ->and($repository->current())->toBeNull();
});

test('deleting removes the connection, and deleting again does nothing', function () {
    $repository = app(YouTubeMusicConnectionRepository::class);
    $connection = YouTubeMusicConnection::factory()->create();

    $repository->deleteForOwner($connection->user_id);
    $repository->deleteForOwner($connection->user_id);

    expect(YouTubeMusicConnection::count())->toBe(0);
});

test('deleting the owner account deletes its connection', function () {
    $connection = YouTubeMusicConnection::factory()->create();

    $connection->user->delete();

    expect(YouTubeMusicConnection::count())->toBe(0);
});
