<?php

namespace Database\Factories;

use App\Enums\SpotifyConnectionStatus;
use App\Models\SpotifyConnection;
use App\Models\User;
use App\Spotify\SpotifyScopes;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SpotifyConnection>
 */
class SpotifyConnectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'connection_id' => (string) Str::uuid(),
            'spotify_user_id' => 'owner-123',
            'display_name' => 'Alice',
            'scopes' => implode(' ', SpotifyScopes::REQUIRED),
            'access_token' => 'BQ-access-123',
            'refresh_token' => 'AQ-refresh-456',
            'access_token_expires_at' => now()->addHour(),
            'authorized_at' => now(),
            'status' => SpotifyConnectionStatus::Connected,
        ];
    }

    /**
     * Spotify access expired or was revoked: no tokens left.
     */
    public function reconnectRequired(): static
    {
        return $this->state(fn () => [
            'access_token' => null,
            'refresh_token' => null,
            'access_token_expires_at' => null,
            'status' => SpotifyConnectionStatus::ReconnectRequired,
        ]);
    }
}
