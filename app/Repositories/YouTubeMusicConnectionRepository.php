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
            ],
        );
    }

    /**
     * Delete the owner's connection and its tokens. Nothing happens when there is none.
     */
    public function deleteForOwner(int $userId): void
    {
        YouTubeMusicConnection::query()->where('user_id', $userId)->delete();
    }
}
