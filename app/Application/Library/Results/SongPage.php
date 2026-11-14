<?php

namespace App\Application\Library\Results;

/**
 * One page of a playlist's songs or of the saved songs (spotify-library-tracks, design D1). The total is the
 * service's count, which includes hidden entries; the next offset is null at the end.
 */
final readonly class SongPage
{
    /**
     * @param  list<PlaylistSongResult|LikedSongResult>  $items
     */
    public function __construct(
        public array $items,
        public ?int $total,
        public ?int $nextOffset,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function itemsArray(): array
    {
        return array_map(fn (PlaylistSongResult|LikedSongResult $item) => $item->toArray(), $this->items);
    }
}
