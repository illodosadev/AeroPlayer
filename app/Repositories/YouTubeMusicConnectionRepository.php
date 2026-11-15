<?php

namespace App\Repositories;

use App\Enums\YouTubeMusicConnectionStatus;
use App\Models\YouTubeMusicConnection;
use App\YouTubeMusic\GoogleTokens;
use App\YouTubeMusic\YouTubeChannel;
use Illuminate\Support\Str;

class YouTubeMusicConnectionRepository
{
    /**
     * The owner's YouTube Music connection, or null when YouTube Music is not connected.
     */
    public function current(): ?YouTubeMusicConnection
    {
        return YouTubeMusicConnection::query()->first();
    }

    /**
     * The connection status, without loading the tokens. Null means not connected.
     */
    public function status(): ?YouTubeMusicConnectionStatus
    {
        return YouTubeMusicConnection::query()->value('status');
    }

    /**
     * Store the owner's connection, replacing any previous one in place with a new connection identifier
     * (youtube-music-connection, design D5).
     */
    public function storeForOwner(int $userId, YouTubeChannel $channel, GoogleTokens $tokens): YouTubeMusicConnection
    {
        return YouTubeMusicConnection::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'connection_id' => (string) Str::uuid(),
                'channel_id' => $channel->id,
                'channel_title' => $channel->title,
                'scopes' => $tokens->scope,
                'access_token' => $tokens->accessToken,
                'refresh_token' => $tokens->refreshToken,
                'access_token_expires_at' => now()->addSeconds($tokens->expiresIn),
                'authorized_at' => now(),
                'status' => YouTubeMusicConnectionStatus::Connected,
                'config_error_at' => null,
            ],
        );
    }

    /**
     * Store a renewed access token and its expiry on the connection with this identifier, the refresh token when
     * Google rotated it, and clear the configuration-error mark (youtube-music-credentials, design D4). The model is
     * saved, not mass-updated, so the encrypted casts apply. A connection replaced meanwhile is left alone.
     */
    public function storeRefreshedTokens(
        string $connectionId,
        #[\SensitiveParameter] string $accessToken,
        #[\SensitiveParameter] ?string $refreshToken,
        int $expiresIn,
    ): void {
        $connection = YouTubeMusicConnection::query()->where('connection_id', $connectionId)->first();

        if ($connection === null) {
            return;
        }

        $connection->access_token = $accessToken;
        $connection->access_token_expires_at = now()->addSeconds($expiresIn);
        $connection->config_error_at = null;

        if ($refreshToken !== null) {
            $connection->refresh_token = $refreshToken;
        }

        $connection->save();
    }

    /**
     * Mark the connection with this identifier as needing a reconnect and delete its tokens. The settings page then
     * asks the owner to reconnect.
     */
    public function markReconnectRequired(string $connectionId): void
    {
        YouTubeMusicConnection::query()->where('connection_id', $connectionId)->update([
            'status' => YouTubeMusicConnectionStatus::ReconnectRequired->value,
            'access_token' => null,
            'refresh_token' => null,
            'access_token_expires_at' => null,
        ]);
    }

    /**
     * Mark the connection with this identifier with a configuration error, keeping the time of the first one.
     */
    public function markConfigError(string $connectionId): void
    {
        YouTubeMusicConnection::query()
            ->where('connection_id', $connectionId)
            ->whereNull('config_error_at')
            ->update(['config_error_at' => now()]);
    }

    /**
     * Delete the owner's connection and its tokens. Nothing happens when there is none.
     */
    public function deleteForOwner(int $userId): void
    {
        YouTubeMusicConnection::query()->where('user_id', $userId)->delete();
    }
}
