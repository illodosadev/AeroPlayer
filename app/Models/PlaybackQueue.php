<?php

namespace App\Models;

use Database\Factories\PlaybackQueueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The owner's playback queue (playback-queue, design D1). Its songs are PlaybackQueueItem rows ordered by position;
 * current_item_id points at one of them, or is null.
 */
class PlaybackQueue extends Model
{
    /** @use HasFactory<PlaybackQueueFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'version',
        'current_item_id',
        'shuffle',
        'repeat',
        'mode',
        'context_uri',
        'context_title',
        'continuation',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'version' => 0,
        'shuffle' => false,
        'repeat' => 'off',
        'mode' => 'queue',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'current_item_id' => 'integer',
            'shuffle' => 'boolean',
            'continuation' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<PlaybackQueueItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PlaybackQueueItem::class)->orderBy('position');
    }
}
