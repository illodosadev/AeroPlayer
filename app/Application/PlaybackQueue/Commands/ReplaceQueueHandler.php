<?php

namespace App\Application\PlaybackQueue\Commands;

use App\Application\PlaybackQueue\Exceptions\QueueFull;
use App\Application\PlaybackQueue\QueueLimit;
use App\Models\PlaybackQueue;
use App\Repositories\PlaybackQueueItemRepository;
use App\Repositories\PlaybackQueueRepository;

final readonly class ReplaceQueueHandler
{
    public function __construct(
        private PlaybackQueueRepository $queues,
        private PlaybackQueueItemRepository $items,
    ) {}

    public function handle(ReplaceQueue $command): void
    {
        $this->queues->writeForOwner($command->userId, function (PlaybackQueue $queue) use ($command) {
            if (count($command->tracks) > QueueLimit::SONGS) {
                throw new QueueFull;
            }

            $ids = $this->items->replaceAll($queue->getKey(), $command->tracks);

            $this->queues->setCurrent($queue, $ids[$command->currentIndex] ?? null);
        });
    }
}
