<?php

namespace App\Application\Library\Queries;

use App\Application\Library\Concerns\ReadsUsableConnection;
use App\Application\Library\Exceptions\PlaylistUnavailable;
use App\Application\Library\Results\SongPage;
use App\Application\Library\SpotifyLibraryReader;
use App\Application\Spotify\Exceptions\SpotifyApiFailed;
use App\Repositories\SpotifyConnectionRepository;

final readonly class GetPlaylistSongsHandler
{
    use ReadsUsableConnection;

    public function __construct(
        private SpotifyConnectionRepository $connections,
        private SpotifyLibraryReader $reader,
    ) {}

    /**
     * @throws PlaylistUnavailable
     * @throws SpotifyApiFailed
     */
    public function handle(GetPlaylistSongs $query): SongPage
    {
        $this->usableConnection($this->connections);

        return $this->reader->playlistSongs($query->id, $query->offset);
    }
}
