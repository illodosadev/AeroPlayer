<?php

namespace App\Application\PlaybackQueue\Queries;

use App\Application\PlaybackQueue\Results\QueueItemResult;
use App\Application\PlaybackQueue\Results\QueueState;
use App\Models\PlaybackQueueItem;
use App\Repositories\PlaybackQueueRepository;

final readonly class GetQueueHandler
{
    public function __construct(private PlaybackQueueRepository $queues) {}

    public function handle(GetQueue $query): QueueState
    {
        $queue = $this->queues->stateForOwner($query->userId);

        if ($queue === null) {
            return QueueState::empty();
        }

        return new QueueState(
            $queue->version,
            $queue->current_item_id,
            $queue->shuffle,
            (string) $queue->repeat,
            (string) $queue->mode,
            $queue->context_uri === null ? null : ['uri' => $queue->context_uri, 'title' => $queue->context_title],
            $queue->continuation !== null,
            $queue->items->map(fn (PlaybackQueueItem $item) => new QueueItemResult(
                $item->getKey(),
                $item->source,
                $item->song_id,
                $item->uri,
                $item->title,
                array_values($item->artists ?? []),
                $item->album,
                $item->duration_ms,
                $item->image,
                $item->origin,
            ))->values()->all(),
        );
    }
}
