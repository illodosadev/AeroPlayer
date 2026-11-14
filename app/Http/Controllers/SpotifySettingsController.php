<?php

namespace App\Http\Controllers;

use App\Application\Spotify\Queries\GetSpotifySettings;
use App\Bus\QueryBus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Spotify settings page (spotify-connection, design D9). The page data comes from GetSpotifySettings; the
 * controller only adds the message flashed by the last connect or disconnect.
 */
class SpotifySettingsController extends Controller
{
    public function __construct(private readonly QueryBus $queries) {}

    public function show(Request $request): Response
    {
        $settings = $this->queries->ask(new GetSpotifySettings);

        return Inertia::render('settings/Spotify', [
            'configured' => $settings->configured,
            'status' => $settings->status,
            'accountName' => $settings->accountName,
            'reconnectBefore' => $settings->reconnectBefore,
            'missingScopes' => $settings->missingScopes,
            'message' => $request->session()->get('spotifyMessage'),
        ]);
    }
}
