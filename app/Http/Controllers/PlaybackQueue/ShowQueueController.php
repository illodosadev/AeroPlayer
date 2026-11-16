<?php

namespace App\Http\Controllers\PlaybackQueue;

use App\Bus\QueryBus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PlaybackQueue\Concerns\RespondsWithQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "GET /queue": the owner's whole queue, in JSON (playback-queue, design D6).
 */
class ShowQueueController extends Controller
{
    use RespondsWithQueue;

    public function __construct(private readonly QueryBus $queries) {}

    public function __invoke(Request $request): JsonResponse
    {
        return $this->queueResponse((int) $request->user()->getKey());
    }
}
