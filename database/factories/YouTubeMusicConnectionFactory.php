<?php

namespace Database\Factories;

use App\Enums\YouTubeMusicConnectionStatus;
use App\Models\User;
use App\Models\YouTubeMusicConnection;
use App\YouTubeMusic\GoogleDeviceFlow;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<YouTubeMusicConnection>
 */
class YouTubeMusicConnectionFactory extends Factory
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
            'channel_id' => 'UC-owner',
            'channel_title' => 'Alice Music',
            'scopes' => GoogleDeviceFlow::SCOPE,
            'access_token' => 'ya29.access-123',
            'refresh_token' => '1//refresh-456',
            'access_token_expires_at' => now()->addHour(),
            'authorized_at' => now(),
            'status' => YouTubeMusicConnectionStatus::Connected,
        ];
    }

    /**
     * YouTube Music access expired or was revoked: no tokens left.
     */
    public function reconnectRequired(): static
    {
        return $this->state(fn () => [
            'access_token' => null,
            'refresh_token' => null,
            'access_token_expires_at' => null,
            'status' => YouTubeMusicConnectionStatus::ReconnectRequired,
        ]);
    }
}
