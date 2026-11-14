<?php

namespace App\Application\Spotify\Queries;

use App\Application\Spotify\Results\SpotifyPlaybackToken;
use App\Bus\Query;

/**
 * A fresh access token for the Web Playback SDK. Reading it may refresh it: the refresh renews a credential the
 * Spotify client manages, it changes nothing the owner sees (spotify-playback-api, design D2).
 *
 * @implements Query<SpotifyPlaybackToken>
 */
final readonly class GetSpotifyPlaybackToken implements Query {}
