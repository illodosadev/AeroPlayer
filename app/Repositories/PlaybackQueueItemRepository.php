<?php

namespace App\Repositories;

use App\Application\PlaybackQueue\QueueTrack;
use App\Models\PlaybackQueue;
use App\Models\PlaybackQueueItem;
use Illuminate\Database\Eloquent\Collection;

/**
 * The songs of a playback queue. Positions are dense, 0 to n-1, and every write that adds, moves or removes songs
 * renumbers them (playback-queue, design D2).
 */
class PlaybackQueueItemRepository
{
    /**
     * @return Collection<int, PlaybackQueueItem>
     */
    public function forQueue(int $queueId): Collection
    {
        return PlaybackQueueItem::query()
            ->where('playback_queue_id', $queueId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function count(int $queueId): int
    {
        return PlaybackQueueItem::query()->where('playback_queue_id', $queueId)->count();
    }

    public function find(int $queueId, int $itemId): ?PlaybackQueueItem
    {
        return PlaybackQueueItem::query()->where('playback_queue_id', $queueId)->whereKey($itemId)->first();
    }

    /**
     * The song right after this one in the queue's order, or null when it is the last.
     */
    public function nextAfter(PlaybackQueueItem $item): ?PlaybackQueueItem
    {
        return PlaybackQueueItem::query()
            ->where('playback_queue_id', $item->playback_queue_id)
            ->where('position', '>', $item->position)
            ->orderBy('position')
            ->first();
    }

    /**
     * Add the songs at the end, in order, and return their new item ids. original_position follows position.
     *
     * @param  list<QueueTrack>  $tracks
     * @return list<int>
     */
    public function append(int $queueId, array $tracks): array
    {
        $start = $this->count($queueId);
        $ids = [];

        foreach ($tracks as $offset => $track) {
            $ids[] = $this->create($queueId, $track, $start + $offset)->getKey();
        }

        return $ids;
    }

    /**
     * Replace every song of the queue and return the new item ids, in order.
     *
     * @param  list<QueueTrack>  $tracks
     * @return list<int>
     */
    public function replaceAll(int $queueId, array $tracks): array
    {
        PlaybackQueueItem::query()->where('playback_queue_id', $queueId)->delete();

        return $this->append($queueId, $tracks);
    }

    /**
     * Move a song to an index of the queue's order, keeping the others in order.
     */
    public function moveTo(PlaybackQueue $queue, PlaybackQueueItem $item, int $index): void
    {
        $ids = $this->forQueue($queue->getKey())->modelKeys();
        $ids = array_values(array_filter($ids, fn (int $id) => $id !== $item->getKey()));
        array_splice($ids, max(0, min($index, count($ids))), 0, [$item->getKey()]);

        $this->applyOrder($ids);
    }

    /**
     * Remove a song and close the gap it leaves.
     */
    public function delete(PlaybackQueueItem $item): void
    {
        $queueId = $item->playback_queue_id;
        $item->delete();

        $this->renumber($queueId);
    }

    /**
     * Number the songs 0 to n-1 in their current order.
     */
    public function renumber(int $queueId): void
    {
        $this->applyOrder($this->forQueue($queueId)->modelKeys());
    }

    /**
     * @param  list<int>  $ids  item ids in their new order
     */
    private function applyOrder(array $ids): void
    {
        $current = PlaybackQueueItem::query()->whereKey($ids)->pluck('position', 'id');

        foreach ($ids as $position => $id) {
            if ((int) $current[$id] !== $position) {
                PlaybackQueueItem::query()->whereKey($id)->update(['position' => $position]);
            }
        }
    }

    private function create(int $queueId, QueueTrack $track, int $position): PlaybackQueueItem
    {
        return PlaybackQueueItem::query()->create([
            'playback_queue_id' => $queueId,
            'position' => $position,
            'original_position' => $position,
            'source' => $track->source,
            'song_id' => $track->id,
            'uri' => $track->uri,
            'title' => $track->title,
            'artists' => $track->artists,
            'album' => $track->album,
            'duration_ms' => $track->durationMs,
            'image' => $track->image,
            'origin' => $track->origin,
        ]);
    }
}
