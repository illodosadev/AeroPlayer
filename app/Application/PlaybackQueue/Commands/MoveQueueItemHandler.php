<?php

namespace App\Application\PlaybackQueue\Commands;

use App\Application\PlaybackQueue\Exceptions\QueueItemNotFound;
use App\Application\PlaybackQueue\Exceptions\QueueItemNotPending;
use App\Application\PlaybackQueue\Exceptions\QueuePositionOutOfRange;
use App\Application\PlaybackQueue\Exceptions\QueueVersionConflict;
use App\Models\PlaybackQueue;
use App\Repositories\PlaybackQueueItemRepository;
use App\Repositories\PlaybackQueueRepository;

final readonly class MoveQueueItemHandler
{
    public function __construct(
        private PlaybackQueueRepository $queues,
        private PlaybackQueueItemRepository $items,
    ) {}

    public function handle(MoveQueueItem $command): void
    {
        $this->queues->writeForOwner($command->userId, function (PlaybackQueue $queue) use ($command) {
            if ($queue->version !== $command->version) {
                throw new QueueVersionConflict;
            }

            $item = $this->items->find($queue->getKey(), $command->itemId)
                ?? throw new QueueItemNotFound;

            // Pending songs come after the current one; with no current song nothing is pending (design D2).
            $current = $queue->current_item_id === null
                ? null
                : $this->items->find($queue->getKey(), $queue->current_item_id);

            if ($current === null || $item->position <= $current->position) {
                throw new QueueItemNotPending;
            }

            $pending = $this->items->count($queue->getKey()) - $current->position - 1;

            if ($command->position >= $pending) {
                throw new QueuePositionOutOfRange;
            }

            $this->items->moveTo($queue, $item, $current->position + 1 + $command->position);
        });
    }
}
