<?php

namespace App\Application\PlaybackQueue\Commands;

use App\Application\PlaybackQueue\Exceptions\QueueFull;
use App\Application\PlaybackQueue\Exceptions\QueueVersionConflict;
use App\Application\PlaybackQueue\QueueLimit;
use App\Models\PlaybackQueue;
use App\Repositories\PlaybackQueueItemRepository;
use App\Repositories\PlaybackQueueRepository;

final readonly class AddQueueItemsHandler
{
    public function __construct(
        private PlaybackQueueRepository $queues,
        private PlaybackQueueItemRepository $items,
    ) {}

    public function handle(AddQueueItems $command): void
    {
        $this->queues->writeForOwner($command->userId, function (PlaybackQueue $queue) use ($command) {
            if ($queue->version !== $command->version) {
                throw new QueueVersionConflict;
            }

            if ($this->items->count($queue->getKey()) + count($command->tracks) > QueueLimit::SONGS) {
                throw new QueueFull;
            }

            $ids = $this->items->append($queue->getKey(), $command->tracks);

            // A song added to a queue with no current song becomes the current one (playback-queue, design D2).
            if ($queue->current_item_id === null) {
                $this->queues->setCurrent($queue, $ids[0] ?? null);
            }
        });
    }
}
