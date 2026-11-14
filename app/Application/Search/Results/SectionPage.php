<?php

namespace App\Application\Search\Results;

/**
 * One page of a search section (spotify-search-api, design D1 and D3). The next offset is null when there is no next
 * page; the entry point turns it into an opaque cursor.
 */
final readonly class SectionPage
{
    /**
     * @param  list<SongResult|AlbumResult|ArtistResult>  $items
     */
    public function __construct(
        public array $items,
        public ?int $nextOffset,
    ) {}
}
