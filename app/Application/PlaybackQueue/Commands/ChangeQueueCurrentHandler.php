<?php

namespace App\Application\PlaybackQueue\Commands;

use App\Application\PlaybackQueue\Exceptions\QueueItemNotFound;
use App\Application\PlaybackQueue\Exceptions\QueueVersionConflict;
use App\Models\PlaybackQueue;
use App\Repositories\PlaybackQueueItemRepository;
use App\Repositories\PlaybackQueueRepository;

final readonly class ChangeQueueCurrentHandler
{
    public function __construct(
        private PlaybackQueueRepository $queues,
        private PlaybackQueueItemRepository $items,
    ) {}

    public function handle(ChangeQueueCurrent $command): void
    {
        $this->queues->writeForOwner($command->userId, function (PlaybackQueue $queue) use ($command) {
            if ($queue->version !== $command->version) {
                throw new QueueVersionConflict;
            }

            $item = $this->items->find($queue->getKey(), $command->itemId)
                ?? throw new QueueItemNotFound;

            $this->queues->setCurrent($queue, $item->getKey());
        });
    }
}
