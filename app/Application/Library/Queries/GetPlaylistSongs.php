<?php

namespace App\Application\Library\Queries;

use App\Application\Library\Results\SongPage;
use App\Bus\Query;

/**
 * One page of a playlist's songs, from an offset (spotify-library-tracks, design D1), for "Load more".
 *
 * @implements Query<SongPage>
 */
final readonly class GetPlaylistSongs implements Query
{
    public function __construct(
        public string $id,
        public int $offset,
    ) {}
}
