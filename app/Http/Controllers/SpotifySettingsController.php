<?php

namespace App\Http\Controllers;

use App\Enums\SpotifyConnectionStatus;
use App\Repositories\SpotifyConnectionRepository;
use App\Spotify\SpotifyOAuth;
use App\Spotify\SpotifyScopes;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Spotify settings page (spotify-connection, design D9). It sends display data only: never a token, an id or
 * the list of scopes, and it never reads the encrypted token columns.
 */
class SpotifySettingsController extends Controller
{
    public function __construct(
        private readonly SpotifyOAuth $oauth,
        private readonly SpotifyConnectionRepository $connections,
    ) {}

    public function show(Request $request): Response
    {
        $connection = $this->connections->current();
        $connected = $connection?->status === SpotifyConnectionStatus::Connected;

        return Inertia::render('settings/Spotify', [
            'configured' => $this->oauth->isConfigured(),
            'status' => $connection?->status->value ?? 'not_connected',
            'accountName' => $connection ? ($connection->display_name ?: $connection->spotify_user_id) : null,
            // Spotify's refresh tokens last 6 months; the no-overflow version never lands past that (design D3).
            'reconnectBefore' => $connected ? $connection->authorized_at->copy()->addMonthsNoOverflow(6)->format('Y-m-d') : null,
            'missingScopes' => $connected && SpotifyScopes::missing($connection->scopes) !== [],
            'message' => $request->session()->get('spotifyMessage'),
        ]);
    }
}
