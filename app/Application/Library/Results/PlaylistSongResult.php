<?php

namespace App\Application\Library\Results;

use App\Application\Search\Results\SongResult;

/**
 * A song inside a playlist (spotify-library-tracks, design D2): the common song keys, plus its position in the
 * service's playlist (counted before hidden entries are skipped) and whether it is a local file.
 */
final readonly class PlaylistSongResult
{
    public function __construct(
        public SongResult $song,
        public int $position,
        public bool $local,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [...$this->song->toArray(), 'position' => $this->position, 'local' => $this->local];
    }
}
