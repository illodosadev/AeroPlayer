<?php

namespace App\Http\Controllers\PlaybackQueue;

use App\Application\PlaybackQueue\Commands\AddQueueItems;
use App\Application\UseCaseFailed;
use App\Bus\CommandBus;
use App\Bus\QueryBus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PlaybackQueue\Concerns\RespondsWithQueue;
use App\Http\Requests\PlaybackQueue\AddQueueItemsRequest;
use Illuminate\Http\JsonResponse;

/**
 * "POST /queue/items": add songs at the end of the queue (playback-queue, design D6).
 */
class AddQueueItemsController extends Controller
{
    use RespondsWithQueue;

    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {}

    public function __invoke(AddQueueItemsRequest $request): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        try {
            $this->commands->dispatch(new AddQueueItems($userId, $request->version(), $request->tracks()));
        } catch (UseCaseFailed $failure) {
            return $this->queueFailure($failure, $userId);
        }

        return $this->queueResponse($userId);
    }
}
