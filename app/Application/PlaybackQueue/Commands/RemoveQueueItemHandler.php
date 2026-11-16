<?php

namespace App\Application\PlaybackQueue\Commands;

use App\Application\PlaybackQueue\Exceptions\QueueItemNotFound;
use App\Application\PlaybackQueue\Exceptions\QueueVersionConflict;
use App\Models\PlaybackQueue;
use App\Repositories\PlaybackQueueItemRepository;
use App\Repositories\PlaybackQueueRepository;

final readonly class RemoveQueueItemHandler
{
    public function __construct(
        private PlaybackQueueRepository $queues,
        private PlaybackQueueItemRepository $items,
    ) {}

    public function handle(RemoveQueueItem $command): void
    {
        $this->queues->writeForOwner($command->userId, function (PlaybackQueue $queue) use ($command) {
            if ($queue->version !== $command->version) {
                throw new QueueVersionConflict;
            }

            $item = $this->items->find($queue->getKey(), $command->itemId)
                ?? throw new QueueItemNotFound;

            // Removing the current song makes the next one current, or leaves none (design D2).
            if ($queue->current_item_id === $item->getKey()) {
                $this->queues->setCurrent($queue, $this->items->nextAfter($item)?->getKey());
            }

            $this->items->delete($item);
        });
    }
}
