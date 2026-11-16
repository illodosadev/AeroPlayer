<?php

namespace Database\Factories;

use App\Models\PlaybackQueue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlaybackQueue>
 */
class PlaybackQueueFactory extends Factory
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
            'version' => 0,
            'current_item_id' => null,
        ];
    }
}
