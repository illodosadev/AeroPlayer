<?php

namespace App\Http\Controllers\Spotify;

use App\Application\Spotify\Exceptions\SpotifyNotConfigured;
use App\Application\Spotify\Queries\GetSpotifyAuthorizationUrl;
use App\Bus\QueryBus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Send the owner to Spotify's authorization page with a new single-use state (spotify-connection, design D5).
 */
class StartSpotifyConnectionController extends Controller
{
    /**
     * The session key of the single-use state, read back by FinishSpotifyConnectionController.
     */
    public const STATE_KEY = 'spotify_oauth_state';

    public function __construct(private readonly QueryBus $queries) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $state = Str::random(64);

        try {
            $url = $this->queries->ask(new GetSpotifyAuthorizationUrl($state));
        } catch (SpotifyNotConfigured) {
            return redirect('/settings/spotify');
        }

        $request->session()->put(self::STATE_KEY, $state);

        return redirect()->away($url);
    }
}
