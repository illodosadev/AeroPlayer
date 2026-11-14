<?php

namespace App\Application\Spotify\Queries;

use App\Application\Spotify\Results\SpotifySettings;
use App\Bus\Query;

/**
 * @implements Query<SpotifySettings>
 */
final readonly class GetSpotifySettings implements Query {}
