<?php

namespace App\Application\Spotify\Queries;

use App\Bus\Query;
use App\Enums\SpotifyConnectionStatus;

/**
 * The Spotify connection status, without loading the tokens. Null means not connected.
 *
 * @implements Query<SpotifyConnectionStatus|null>
 */
final readonly class GetSpotifyStatus implements Query {}
