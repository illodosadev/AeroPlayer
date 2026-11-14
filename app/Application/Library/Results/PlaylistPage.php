<?php

namespace App\Application\Library\Results;

/**
 * A playlist and its first page of songs (spotify-library-tracks, design D1). The songs are null when the service
 * does not let the app list them: a playlist the owner only follows.
 */
final readonly class PlaylistPage
{
    public function __construct(
        public PlaylistResult $playlist,
        public ?SongPage $songs,
    ) {}
}
