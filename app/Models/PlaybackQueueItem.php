<?php

namespace App\Models;

use Database\Factories\PlaybackQueueItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One song of a playback queue, in the app's common format (playback-queue, design D1). The song's own id is stored
 * as song_id, since id is the item's key; origin is opaque and returned as it was sent.
 */
class PlaybackQueueItem extends Model
{
    /** @use HasFactory<PlaybackQueueItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'playback_queue_id',
        'position',
        'original_position',
        'source',
        'song_id',
        'uri',
        'title',
        'artists',
        'album',
        'duration_ms',
        'image',
        'origin',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'original_position' => 'integer',
            'duration_ms' => 'integer',
            'artists' => 'array',
            'origin' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PlaybackQueue, $this>
     */
    public function queue(): BelongsTo
    {
        return $this->belongsTo(PlaybackQueue::class, 'playback_queue_id');
    }
}
