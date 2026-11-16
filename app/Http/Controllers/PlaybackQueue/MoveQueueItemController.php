<?php

namespace App\Http\Controllers\PlaybackQueue;

use App\Application\PlaybackQueue\Commands\MoveQueueItem;
use App\Application\UseCaseFailed;
use App\Bus\CommandBus;
use App\Bus\QueryBus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PlaybackQueue\Concerns\RespondsWithQueue;
use App\Http\Requests\PlaybackQueue\MoveQueueItemRequest;
use Illuminate\Http\JsonResponse;

/**
 * "PATCH /queue/items/{item}": move a pending song among the pending songs (playback-queue, design D6).
 */
class MoveQueueItemController extends Controller
{
    use RespondsWithQueue;

    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {}

    public function __invoke(MoveQueueItemRequest $request, string $item): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        try {
            $this->commands->dispatch(new MoveQueueItem($userId, $request->version(), (int) $item, $request->position()));
        } catch (UseCaseFailed $failure) {
            return $this->queueFailure($failure, $userId);
        }

        return $this->queueResponse($userId);
    }
}
