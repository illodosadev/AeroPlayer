<?php

namespace App\Spotify;

/**
 * The Spotify scopes the v1 features need (spotify-connection, design D6).
 */
final class SpotifyScopes
{
    /**
     * @var list<string>
     */
    public const REQUIRED = [
        'streaming',
        'user-read-email',
        'user-read-private',
        'user-modify-playback-state',
        'user-read-playback-state',
        'user-read-currently-playing',
        'playlist-read-private',
        'playlist-read-collaborative',
        'user-library-read',
    ];

    /**
     * The required scopes missing from a space-separated list of granted scopes.
     *
     * @return list<string>
     */
    public static function missing(string $granted): array
    {
        $grantedScopes = preg_split('/\s+/', trim($granted), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_diff(self::REQUIRED, $grantedScopes));
    }
}
