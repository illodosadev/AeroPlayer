<?php

namespace App\Http\Controllers\Spotify;

use App\Application\Spotify\Commands\DisconnectSpotify;
use App\Bus\CommandBus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Spotify\Concerns\RedirectsToSpotifySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Delete the stored connection and its tokens. Spotify has no revocation endpoint, so the message points to the
 * account page where the owner can remove the app's access.
 */
class DisconnectSpotifyController extends Controller
{
    use RedirectsToSpotifySettings;

    public function __construct(private readonly CommandBus $commands) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $this->commands->dispatch(new DisconnectSpotify($request->user()->getKey()));

        return $this->toSettings(
            'success',
            'Spotify disconnected.',
            'You can also remove AeroPlayer from your Spotify account at spotify.com/account/apps.',
        );
    }
}
