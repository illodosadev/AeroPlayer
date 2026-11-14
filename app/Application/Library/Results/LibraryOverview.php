<?php

namespace App\Application\Library\Results;

/**
 * The library overview (spotify-library-overview, design D1): the playlists and the saved-song count. A part the
 * owner did not grant the permission for is null, and its "allowed" flag is false.
 */
final readonly class LibraryOverview
{
    /**
     * @param  list<PlaylistResult>|null  $playlists
     */
    public function __construct(
        public ?array $playlists,
        public bool $playlistsAllowed,
        public ?int $likedTotal,
        public bool $likedAllowed,
    ) {}
}
