<?php

namespace App\Application\Library\Queries;

use App\Application\Library\Results\LikedSongsPage;
use App\Bus\Query;

/**
 * One page of the saved songs, from an offset (spotify-library-tracks, design D1).
 *
 * @implements Query<LikedSongsPage>
 */
final readonly class GetLikedSongs implements Query
{
    public function __construct(public int $offset = 0) {}
}
