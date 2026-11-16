<?php

namespace App\Repositories;

use App\Models\PlaybackQueue;
use Illuminate\Support\Facades\DB;

class PlaybackQueueRepository
{
    /**
     * The owner's queue with its songs ordered by position, or null when the owner never used the queue.
     */
    public function stateForOwner(int $userId): ?PlaybackQueue
    {
        return PlaybackQueue::query()->with('items')->where('user_id', $userId)->first();
    }

    /**
     * Run a write on the owner's queue inside a transaction, with its row locked (playback-queue, design D3). The row is
     * created first when it is missing. When the callback returns, the version goes up by one; when it throws, nothing
     * is kept and the version stays.
     *
     * @param  callable(PlaybackQueue): void  $write
     */
    public function writeForOwner(int $userId, callable $write): void
    {
        DB::transaction(function () use ($userId, $write) {
            if (! PlaybackQueue::query()->where('user_id', $userId)->exists()) {
                // insertOrIgnore, so two first writes at once cannot fail on the unique user_id.
                PlaybackQueue::query()->insertOrIgnore([
                    'user_id' => $userId,
                    'version' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $queue = PlaybackQueue::query()->where('user_id', $userId)->lockForUpdate()->firstOrFail();

            $write($queue);

            $queue->version = $queue->version + 1;
            $queue->save();
        });
    }

    /**
     * Point the queue at its current song, or at none. The caller's write saves it (writeForOwner).
     */
    public function setCurrent(PlaybackQueue $queue, ?int $itemId): void
    {
        $queue->current_item_id = $itemId;
    }
}
