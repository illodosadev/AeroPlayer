<?php

namespace App\Application\Library\Queries;

use App\Application\Library\Results\PlaylistPage;
use App\Bus\Query;

/**
 * A playlist and, when the service lets the app list them, its first 50 songs (spotify-library-tracks, design D1).
 *
 * @implements Query<PlaylistPage>
 */
final readonly class GetPlaylist implements Query
{
    public function __construct(public string $id) {}
}
