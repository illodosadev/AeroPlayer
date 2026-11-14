<?php

namespace App\Http\Controllers;

use App\Repositories\SpotifyConnectionRepository;
use App\Spotify\ConnectFailure;
use App\Spotify\SpotifyAccount;
use App\Spotify\SpotifyOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Connecting and disconnecting the owner's Spotify account (spotify-connection, design D5, D7 and D8).
 */
class SpotifyConnectionController extends Controller
{
    private const STATE_KEY = 'spotify_oauth_state';

    public function __construct(
        private readonly SpotifyOAuth $oauth,
        private readonly SpotifyConnectionRepository $connections,
    ) {}

    /**
     * Send the owner to Spotify's authorization page with a new single-use state.
     */
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->oauth->isConfigured()) {
            return redirect('/settings/spotify');
        }

        $state = Str::random(64);
        $request->session()->put(self::STATE_KEY, $state);

        return redirect()->away($this->oauth->authorizeUrl($state));
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

        $tokens = $this->oauth->exchangeCode($code);

        if ($tokens instanceof ConnectFailure) {
            return $this->failed($tokens);
        }

        $account = $this->oauth->fetchAccount($tokens->accessToken);

        if ($account instanceof ConnectFailure) {
            return $this->failed($account);
        }

        $refusal = $this->refusal($account);

        if ($refusal !== null) {
            return $this->failed($refusal);
        }

        $this->connections->storeForOwner($request->user(), $account, $tokens);

        return $this->toSettings('success', 'Spotify connected.');
    }

    /**
     * Delete the stored connection and its tokens. Spotify has no revocation endpoint, so the message points to
     * the account page where the owner can remove the app's access.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $this->connections->deleteForOwner($request->user());

        return $this->toSettings(
            'success',
            'Spotify disconnected.',
            'You can also remove AeroPlayer from your Spotify account at spotify.com/account/apps.',
        );
    }

    /**
     * Only the configured owner's account, and only with Premium when Spotify reports the product.
     */
    private function refusal(SpotifyAccount $account): ?ConnectFailure
    {
        if ($account->id !== $this->oauth->ownerId()) {
            return ConnectFailure::NotOwner;
        }

        if ($account->product !== null && $account->product !== 'premium') {
            return ConnectFailure::NotPremium;
        }

        return null;
    }

    private function failed(ConnectFailure $failure): RedirectResponse
    {
        return $this->toSettings($failure->tone(), $failure->message());
    }

    /**
     * The page message is a session flash read by the settings page (design D8).
     */
    private function toSettings(string $tone, string $text, ?string $detail = null): RedirectResponse
    {
        return redirect('/settings/spotify')->with('spotifyMessage', array_filter(
            ['tone' => $tone, 'text' => $text, 'detail' => $detail],
            fn ($value) => $value !== null,
        ));
    }
}
