<?php

namespace App\Http\Controllers\PlaybackQueue\Concerns;

use App\Application\PlaybackQueue\Exceptions\QueueFull;
use App\Application\PlaybackQueue\Exceptions\QueueItemNotFound;
use App\Application\PlaybackQueue\Exceptions\QueueItemNotPending;
use App\Application\PlaybackQueue\Exceptions\QueuePositionOutOfRange;
use App\Application\PlaybackQueue\Exceptions\QueueVersionConflict;
use App\Application\PlaybackQueue\Queries\GetQueue;
use App\Application\UseCaseFailed;
use Illuminate\Http\JsonResponse;

/**
 * The JSON answers of the queue API (playback-queue, design D6): the full queue after a read or an accepted write,
 * and an "error" code for each failure, the version conflict carrying the current queue. Never cached. The using
 * controller injects the query bus as $queries.
 */
trait RespondsWithQueue
{
    protected function queueResponse(int $userId): JsonResponse
    {
        return response()->json($this->queries->ask(new GetQueue($userId))->toArray(), 200, $this->noStore());
    }

    protected function queueFailure(UseCaseFailed $failure, int $userId): JsonResponse
    {
        if ($failure instanceof QueueVersionConflict) {
            return response()->json([
                'error' => 'queue_version_conflict',
                'queue' => $this->queries->ask(new GetQueue($userId))->toArray(),
            ], 409, $this->noStore());
        }

        [$status, $code] = match (true) {
            $failure instanceof QueueFull => [422, 'queue_full'],
            $failure instanceof QueueItemNotFound => [404, 'queue_item_not_found'],
            $failure instanceof QueueItemNotPending => [422, 'item_not_pending'],
            $failure instanceof QueuePositionOutOfRange => [422, 'position_out_of_range'],
            default => throw $failure,
        };

        return response()->json(['error' => $code], $status, $this->noStore());
    }

    /**
     * @return array<string, string>
     */
    protected function noStore(): array
    {
        return ['Cache-Control' => 'no-store, private'];
    }
}
