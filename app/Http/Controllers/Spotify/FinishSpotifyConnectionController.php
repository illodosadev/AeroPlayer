<?php

namespace App\Http\Controllers\Spotify;

use App\Application\Spotify\Commands\ConnectSpotify;
use App\Application\Spotify\Exceptions\SpotifyConnectionFailed;
use App\Bus\CommandBus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Spotify\Concerns\RedirectsToSpotifySettings;
use App\Spotify\ConnectFailure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Spotify's answer (spotify-connection, design D5). The controller keeps the HTTP part: the single-use state, checked
 * first and before any request to Spotify, and Spotify's query parameters. ConnectSpotify does the rest.
 */
class FinishSpotifyConnectionController extends Controller
{
    use RedirectsToSpotifySettings;

    public function __construct(private readonly CommandBus $commands) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $expected = $request->session()->pull(StartSpotifyConnectionController::STATE_KEY);
        $given = $request->query('state');

        if (! is_string($expected) || ! is_string($given) || ! hash_equals($expected, $given)) {
            return $this->failed(ConnectFailure::InvalidState);
        }

        if ($request->query('error') !== null) {
            return $this->failed(
                $request->query('error') === 'access_denied' ? ConnectFailure::Cancelled : ConnectFailure::ExchangeRejected
            );
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return $this->failed(ConnectFailure::ExchangeRejected);
        }

        try {
            $this->commands->dispatch(new ConnectSpotify($request->user()->getKey(), $code));
        } catch (SpotifyConnectionFailed $failure) {
            return $this->failed($failure->reason);
        }

        return $this->toSettings('success', 'Spotify connected.');
    }
}
