<?php

namespace App\Repositories;

use App\Enums\SpotifyConnectionStatus;
use App\Models\SpotifyConnection;
use App\Models\User;
use App\Spotify\SpotifyAccount;
use App\Spotify\SpotifyTokens;
use Illuminate\Support\Str;

class SpotifyConnectionRepository
{
    /**
     * The owner's Spotify connection, or null when Spotify is not connected.
     */
    public function current(): ?SpotifyConnection
    {
        return SpotifyConnection::query()->first();
    }

    /**
     * The connection status, without loading the tokens. Null means not connected.
     */
    public function status(): ?SpotifyConnectionStatus
    {
        return SpotifyConnection::query()->value('status');
    }

    /**
     * Store the owner's connection, replacing any previous one in place with a new connection identifier.
     */
    public function storeForOwner(User $owner, SpotifyAccount $account, SpotifyTokens $tokens): SpotifyConnection
    {
        return SpotifyConnection::query()->updateOrCreate(
            ['user_id' => $owner->getKey()],
            [
                'connection_id' => (string) Str::uuid(),
                'spotify_user_id' => $account->id,
                'display_name' => $account->displayName,
                'scopes' => $tokens->scope,
                'access_token' => $tokens->accessToken,
                'refresh_token' => $tokens->refreshToken,
                'access_token_expires_at' => now()->addSeconds($tokens->expiresIn),
                'authorized_at' => now(),
                'status' => SpotifyConnectionStatus::Connected,
            ],
        );
    }

    /**
     * Delete the owner's connection and its tokens. Nothing happens when there is none.
     */
    public function deleteForOwner(User $owner): void
    {
        SpotifyConnection::query()->where('user_id', $owner->getKey())->delete();
    }
}
