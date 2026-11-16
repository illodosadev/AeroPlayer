<?php

namespace Database\Factories;

use App\Models\PlaybackQueue;
use App\Models\PlaybackQueueItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlaybackQueueItem>
 */
class PlaybackQueueItemFactory extends Factory
{
    /**
     * Define the model's default state: a Spotify song with a valid 22-character track id.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $id = Str::random(22);

        return [
            'playback_queue_id' => PlaybackQueue::factory(),
            'position' => 0,
            'original_position' => 0,
            'source' => 'spotify',
            'song_id' => $id,
            'uri' => "spotify:track:{$id}",
            'title' => fake()->words(2, true),
            'artists' => [fake()->name()],
            'album' => fake()->words(3, true),
            'duration_ms' => 180000,
            'image' => null,
            'origin' => null,
        ];
    }
}
