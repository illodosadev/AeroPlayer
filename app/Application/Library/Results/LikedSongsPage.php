<?php

namespace App\Application\Library\Results;

/**
 * A page of saved songs (spotify-library-tracks, design D1). Without the saved-songs permission nothing is read:
 * allowed is false and the songs are null.
 */
final readonly class LikedSongsPage
{
    public function __construct(
        public bool $allowed,
        public ?SongPage $songs,
    ) {}
}
