<?php

namespace App\Http\Controllers;

use App\Application\Spotify\Commands\ConnectSpotify;
use App\Application\Spotify\Commands\DisconnectSpotify;
use App\Application\Spotify\Exceptions\SpotifyConnectionFailed;
use App\Application\Spotify\Exceptions\SpotifyNotConfigured;
use App\Application\Spotify\Queries\GetSpotifyAuthorizationUrl;
use App\Bus\CommandBus;
use App\Bus\QueryBus;
use App\Spotify\ConnectFailure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Connecting and disconnecting the owner's Spotify account (spotify-connection, design D5, D7 and D8). The
 * controller keeps the HTTP part: the single-use state in the session, Spotify's query parameters and the page
 * message. The use cases are commands and queries (adopt-cqrs, design D5).
 */
class SpotifyConnectionController extends Controller
{
    private const STATE_KEY = 'spotify_oauth_state';

    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {}

    /**
     * Send the owner to Spotify's authorization page with a new single-use state.
     */
    public function redirect(Request $request): RedirectResponse
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

    /**
     * Spotify's answer. The state is checked first, before anything else and before any request to Spotify.
     */
    public function callback(Request $request): RedirectResponse
    {
        $expected = $request->session()->pull(self::STATE_KEY);
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

    /**
     * Delete the stored connection and its tokens. Spotify has no revocation endpoint, so the message points to
     * the account page where the owner can remove the app's access.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $this->commands->dispatch(new DisconnectSpotify($request->user()->getKey()));

        return $this->toSettings(
            'success',
            'Spotify disconnected.',
            'You can also remove AeroPlayer from your Spotify account at spotify.com/account/apps.',
        );
    }

    private function failed(ConnectFailure $failure): RedirectResponse
    {
        return $this->toSettings($failure->tone(), $failure->message());
    }

    /**
     * The page message is a session flash read by the settings page (spotify-connection, design D8).
     */
    private function toSettings(string $tone, string $text, ?string $detail = null): RedirectResponse
    {
        return redirect('/settings/spotify')->with('spotifyMessage', array_filter(
            ['tone' => $tone, 'text' => $text, 'detail' => $detail],
            fn ($value) => $value !== null,
        ));
    }
}
