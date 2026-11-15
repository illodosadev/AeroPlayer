<?php

namespace App\Http\Controllers\YouTubeMusic;

use App\Application\YouTubeMusic\Commands\StartYouTubeMusicConnection;
use App\Application\YouTubeMusic\Exceptions\YouTubeMusicConnectionFailed;
use App\Application\YouTubeMusic\Exceptions\YouTubeMusicNotConfigured;
use App\Bus\CommandBus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\YouTubeMusic\Concerns\RedirectsToYouTubeMusicSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Start the device flow: Google's user code then shows on the settings page (youtube-music-connection, design D2).
 */
class StartYouTubeMusicConnectionController extends Controller
{
    use RedirectsToYouTubeMusicSettings;

    public function __construct(private readonly CommandBus $commands) {}

    public function __invoke(Request $request): RedirectResponse
    {
        try {
            $this->commands->dispatch(new StartYouTubeMusicConnection);
        } catch (YouTubeMusicNotConfigured) {
            // The settings page already says so.
        } catch (YouTubeMusicConnectionFailed $failed) {
            $this->flashFailure($request, $failed->reason);
        }

        return $this->toSettings();
    }
}
