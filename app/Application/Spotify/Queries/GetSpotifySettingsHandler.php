<?php

namespace App\Application\Spotify\Queries;

use App\Application\Spotify\Results\SpotifySettings;
use App\Enums\SpotifyConnectionStatus;
use App\Repositories\SpotifyConnectionRepository;
use App\Spotify\SpotifyOAuth;
use App\Spotify\SpotifyScopes;

final readonly class GetSpotifySettingsHandler
{
    public function __construct(
        private SpotifyOAuth $oauth,
        private SpotifyConnectionRepository $connections,
    ) {}

    public function handle(GetSpotifySettings $query): SpotifySettings
    {
        $connection = $this->connections->current();
        $connected = $connection?->status === SpotifyConnectionStatus::Connected;

        return new SpotifySettings(
            configured: $this->oauth->isConfigured(),
            status: $connection?->status->value ?? 'not_connected',
            accountName: $connection ? ($connection->display_name ?: $connection->spotify_user_id) : null,
            // Spotify's refresh tokens last 6 months; the no-overflow version never lands past that
            // (spotify-connection, design D3).
            reconnectBefore: $connected ? $connection->authorized_at->copy()->addMonthsNoOverflow(6)->format('Y-m-d') : null,
            missingScopes: $connected && SpotifyScopes::missing($connection->scopes) !== [],
        );
    }
}
