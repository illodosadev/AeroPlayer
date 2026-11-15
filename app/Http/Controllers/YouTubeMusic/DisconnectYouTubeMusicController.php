<?php

namespace App\Http\Controllers\YouTubeMusic;

use App\Application\YouTubeMusic\Commands\DisconnectYouTubeMusic;
use App\Bus\CommandBus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\YouTubeMusic\Concerns\RedirectsToYouTubeMusicSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Revoke the access at Google when it answers, and delete the stored connection either way (youtube-music-connection,
 * design D6). The message also points to the Google account page, in case the revocation did not go through.
 */
class DisconnectYouTubeMusicController extends Controller
{
    use RedirectsToYouTubeMusicSettings;

    public function __construct(private readonly CommandBus $commands) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $this->commands->dispatch(new DisconnectYouTubeMusic($request->user()->getKey()));

        $this->flashMessage(
            $request,
            'success',
            'YouTube Music disconnected.',
            'You can also check that AeroPlayer no longer has access at myaccount.google.com/permissions.',
        );

        return $this->toSettings();
    }
}
