<?php

namespace App\Http\Controllers\PlaybackQueue;

use App\Application\PlaybackQueue\Commands\ReplaceQueue;
use App\Application\UseCaseFailed;
use App\Bus\CommandBus;
use App\Bus\QueryBus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PlaybackQueue\Concerns\RespondsWithQueue;
use App\Http\Requests\PlaybackQueue\ReplaceQueueRequest;
use Illuminate\Http\JsonResponse;

/**
 * "PUT /queue": replace every song of the queue, with no version check (playback-queue, design D6).
 */
class ReplaceQueueController extends Controller
{
    use RespondsWithQueue;

    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {}

    public function __invoke(ReplaceQueueRequest $request): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        try {
            $this->commands->dispatch(new ReplaceQueue($userId, $request->tracks(), $request->currentIndex()));
        } catch (UseCaseFailed $failure) {
            return $this->queueFailure($failure, $userId);
        }

        return $this->queueResponse($userId);
    }
}
