<?php

namespace App\Http\Controllers\YouTubeMusic;

use App\Application\YouTubeMusic\Commands\CheckYouTubeMusicApproval;
use App\Application\YouTubeMusic\Exceptions\YouTubeMusicConnectionFailed;
use App\Application\YouTubeMusic\Queries\GetYouTubeMusicConnectStatus;
use App\Bus\CommandBus;
use App\Bus\QueryBus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\YouTubeMusic\Concerns\RedirectsToYouTubeMusicSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The page's polling endpoint (youtube-music-connection, design D3): a check that may call Google, then the status.
 * An attempt that ends without a connection flashes its message for the page's next load.
 */
class ShowYouTubeMusicConnectStatusController extends Controller
{
    use RedirectsToYouTubeMusicSettings;

    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $this->commands->dispatch(new CheckYouTubeMusicApproval($request->user()->getKey()));
        } catch (YouTubeMusicConnectionFailed $failed) {
            $this->flashFailure($request, $failed->reason);

            return $this->answer($failed->reason->status());
        }

        return $this->answer($this->queries->ask(new GetYouTubeMusicConnectStatus));
    }

    private function answer(string $status): JsonResponse
    {
        return response()->json(['status' => $status], 200, ['Cache-Control' => 'no-store, private']);
    }
}
