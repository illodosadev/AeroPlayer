<?php

namespace App\Http\Controllers\PlaybackQueue;

use App\Application\PlaybackQueue\Commands\ChangeQueueCurrent;
use App\Application\UseCaseFailed;
use App\Bus\CommandBus;
use App\Bus\QueryBus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PlaybackQueue\Concerns\RespondsWithQueue;
use App\Http\Requests\PlaybackQueue\ChangeQueueCurrentRequest;
use Illuminate\Http\JsonResponse;

/**
 * "PATCH /queue": move the queue's current song (playback-queue, design D6).
 */
class ChangeQueueCurrentController extends Controller
{
    use RespondsWithQueue;

    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {}

    public function __invoke(ChangeQueueCurrentRequest $request): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        try {
            $this->commands->dispatch(new ChangeQueueCurrent($userId, $request->version(), $request->currentItemId()));
        } catch (UseCaseFailed $failure) {
            return $this->queueFailure($failure, $userId);
        }

        return $this->queueResponse($userId);
    }
}
