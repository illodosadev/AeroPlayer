<?php

namespace App\Application\Search\Queries;

use App\Application\Search\Results\SearchResults;
use App\Application\Search\SearchSection;
use App\Bus\Query;

/**
 * Search the owner's music services for songs, albums and artists (spotify-search-api, design D1). Without a section
 * it answers the first page of all three; with one, that section's page from the offset.
 *
 * @implements Query<SearchResults>
 */
final readonly class SearchMusic implements Query
{
    /**
     * Results per section and page: the most Spotify allows in development mode.
     */
    public const PAGE_SIZE = 10;

    /**
     * Spotify's highest search offset; no page starts beyond it.
     */
    public const MAX_OFFSET = 1000;

    /**
     * @param  string  $query  the trimmed query, 1 to 200 characters
     */
    public function __construct(
        public string $query,
        public ?SearchSection $section = null,
        public int $offset = 0,
    ) {}
}
