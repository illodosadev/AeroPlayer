<?php

namespace App\Application\Library\Queries;

use App\Application\Library\Concerns\ReadsUsableConnection;
use App\Application\Library\Results\LikedSongsPage;
use App\Application\Library\SpotifyLibraryReader;
use App\Application\Spotify\Exceptions\SpotifyApiFailed;
use App\Repositories\SpotifyConnectionRepository;
use App\Spotify\SpotifyScopes;

final readonly class GetLikedSongsHandler
{
    use ReadsUsableConnection;

    public function __construct(
        private SpotifyConnectionRepository $connections,
        private SpotifyLibraryReader $reader,
    ) {}

    /**
     * Without the saved-songs permission nothing is sent to Spotify.
     *
     * @throws SpotifyApiFailed
     */
    public function handle(GetLikedSongs $query): LikedSongsPage
    {
        $connection = $this->usableConnection($this->connections);

        if (! SpotifyScopes::grants($connection->scopes, 'user-library-read')) {
            return new LikedSongsPage(allowed: false, songs: null);
        }

        return new LikedSongsPage(allowed: true, songs: $this->reader->likedSongs($query->offset));
    }
}
