<?php

namespace App\Http\Controllers\PlaybackQueue;

use App\Application\PlaybackQueue\Commands\RemoveQueueItem;
use App\Application\UseCaseFailed;
use App\Bus\CommandBus;
use App\Bus\QueryBus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PlaybackQueue\Concerns\RespondsWithQueue;
use App\Http\Requests\PlaybackQueue\RemoveQueueItemRequest;
use Illuminate\Http\JsonResponse;

/**
 * "DELETE /queue/items/{item}?version=": remove a song from the queue (playback-queue, design D6).
 */
class RemoveQueueItemController extends Controller
{
    use RespondsWithQueue;

    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {}

    public function __invoke(RemoveQueueItemRequest $request, string $item): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        try {
            $this->commands->dispatch(new RemoveQueueItem($userId, $request->version(), (int) $item));
        } catch (UseCaseFailed $failure) {
            return $this->queueFailure($failure, $userId);
        }

        return $this->queueResponse($userId);
    }
}
