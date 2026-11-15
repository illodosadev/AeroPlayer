<?php

namespace App\Http\Controllers\YouTubeMusic;

use App\Application\YouTubeMusic\Queries\GetYouTubeMusicSettings;
use App\Bus\QueryBus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The YouTube Music settings page (youtube-music-connection, design D7 and D8). The page data comes from
 * GetYouTubeMusicSettings; the controller only adds the message flashed by the last connect, check or disconnect.
 */
class ShowYouTubeMusicSettingsController extends Controller
{
    public function __construct(private readonly QueryBus $queries) {}

    public function __invoke(Request $request): Response
    {
        $settings = $this->queries->ask(new GetYouTubeMusicSettings);

        return Inertia::render('settings/YouTubeMusic', [
            'configured' => $settings->configured,
            'status' => $settings->status,
            'channelName' => $settings->channelName,
            'waiting' => $settings->waiting,
            'misconfigured' => $settings->misconfigured,
            'message' => $request->session()->get('youtubeMusicMessage'),
        ]);
    }
}
