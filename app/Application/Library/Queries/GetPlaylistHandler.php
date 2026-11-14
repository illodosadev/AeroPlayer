<?php

namespace App\Application\Library\Queries;

use App\Application\Library\Concerns\ReadsUsableConnection;
use App\Application\Library\Exceptions\PlaylistUnavailable;
use App\Application\Library\Results\PlaylistPage;
use App\Application\Library\SpotifyLibraryReader;
use App\Application\Spotify\Exceptions\SpotifyApiFailed;
use App\Repositories\SpotifyConnectionRepository;

final readonly class GetPlaylistHandler
{
    use ReadsUsableConnection;

    public function __construct(
        private SpotifyConnectionRepository $connections,
        private SpotifyLibraryReader $reader,
    ) {}

    /**
     * Read the playlist, then its first page of songs only when the owner owns it or collaborates on it.
     *
     * @throws PlaylistUnavailable
     * @throws SpotifyApiFailed
     */
    public function handle(GetPlaylist $query): PlaylistPage
    {
        $connection = $this->usableConnection($this->connections);
        $playlist = $this->reader->playlist($query->id, $connection->spotify_user_id);

        return new PlaylistPage(
            $playlist,
            $playlist->canListTracks ? $this->reader->playlistSongs($query->id, 0) : null,
        );
    }
}
