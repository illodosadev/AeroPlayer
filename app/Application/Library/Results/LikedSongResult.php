<?php

namespace App\Application\Library\Results;

use App\Application\Search\Results\SongResult;

/**
 * A saved song (spotify-library-tracks, design D2): the common song keys, plus when it was saved (ISO 8601).
 */
final readonly class LikedSongResult
{
    public function __construct(
        public SongResult $song,
        public string $addedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [...$this->song->toArray(), 'added_at' => $this->addedAt];
    }
}
