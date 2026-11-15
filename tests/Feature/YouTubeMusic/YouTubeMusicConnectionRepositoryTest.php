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

// youtube-music-credentials, task 1.1 (design D4): renewed tokens, reconnecting and the configuration-error mark.

test('storing renewed tokens saves the access token and its expiry, encrypted, and clears the mark', function () {
    $this->freezeSecond();
    $connection = YouTubeMusicConnection::factory()->create(['config_error_at' => now()->subHour()]);

    app(YouTubeMusicConnectionRepository::class)->storeRefreshedTokens($connection->connection_id, 'ya29.new-789', '1//refresh-777', 3599);

    $stored = $connection->fresh();
    $raw = implode(' ', array_map('strval', (array) DB::table('youtube_music_connections')->first()));

    expect($stored->access_token)->toBe('ya29.new-789')
        ->and($stored->refresh_token)->toBe('1//refresh-777')
        ->and($stored->access_token_expires_at->equalTo(now()->addSeconds(3599)))->toBeTrue()
        ->and($stored->config_error_at)->toBeNull()
        ->and($raw)->not->toContain('ya29.new-789')->not->toContain('1//refresh-777');
});

test('storing renewed tokens without a new refresh token keeps the stored one', function () {
    $connection = YouTubeMusicConnection::factory()->create();

    app(YouTubeMusicConnectionRepository::class)->storeRefreshedTokens($connection->connection_id, 'ya29.new-789', null, 3599);

    expect($connection->fresh()->refresh_token)->toBe('1//refresh-456')
        ->and($connection->fresh()->access_token)->toBe('ya29.new-789');
});

test('marking for reconnecting deletes the tokens and the expiry', function () {
    $connection = YouTubeMusicConnection::factory()->create();

    app(YouTubeMusicConnectionRepository::class)->markReconnectRequired($connection->connection_id);

    $stored = $connection->fresh();

    expect($stored->status)->toBe(YouTubeMusicConnectionStatus::ReconnectRequired)
        ->and($stored->access_token)->toBeNull()
        ->and($stored->refresh_token)->toBeNull()
        ->and($stored->access_token_expires_at)->toBeNull();
});

test('the configuration-error mark is set once and keeps its first time', function () {
    $this->travelTo('2026-11-15 10:00:00');
    $connection = YouTubeMusicConnection::factory()->create();
    $repository = app(YouTubeMusicConnectionRepository::class);

    $repository->markConfigError($connection->connection_id);
    $this->travelTo('2026-11-15 11:00:00');
    $repository->markConfigError($connection->connection_id);

    $stored = $connection->fresh();

    expect($stored->config_error_at->toDateTimeString())->toBe('2026-11-15 10:00:00')
        ->and($stored->status)->toBe(YouTubeMusicConnectionStatus::Connected)
        ->and($stored->access_token)->toBe('ya29.access-123');
});

test('each write leaves a connection with another identifier alone', function () {
    $connection = YouTubeMusicConnection::factory()->create();
    $repository = app(YouTubeMusicConnectionRepository::class);

    $repository->storeRefreshedTokens('another-connection', 'ya29.new-789', '1//refresh-777', 3599);
    $repository->markReconnectRequired('another-connection');
    $repository->markConfigError('another-connection');

    $stored = $connection->fresh();

    expect($stored->status)->toBe(YouTubeMusicConnectionStatus::Connected)
        ->and($stored->access_token)->toBe('ya29.access-123')
        ->and($stored->refresh_token)->toBe('1//refresh-456')
        ->and($stored->config_error_at)->toBeNull();
});

test('storing the owner connection clears the configuration-error mark', function () {
    $existing = YouTubeMusicConnection::factory()->create(['config_error_at' => now()]);

    app(YouTubeMusicConnectionRepository::class)->storeForOwner($existing->user_id, ownerChannel(), googleTokens());

    expect(YouTubeMusicConnection::sole()->config_error_at)->toBeNull();
});

test('the token parameters of storeRefreshedTokens are sensitive', function () {
    $sensitive = fn (string $name) => (new ReflectionParameter([YouTubeMusicConnectionRepository::class, 'storeRefreshedTokens'], $name))
        ->getAttributes(SensitiveParameter::class) !== [];

    expect($sensitive('accessToken'))->toBeTrue()
        ->and($sensitive('refreshToken'))->toBeTrue()
        ->and($sensitive('connectionId'))->toBeFalse();
});
