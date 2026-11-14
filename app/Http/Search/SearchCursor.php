<?php

namespace App\Http\Search;

use App\Application\Search\Queries\SearchMusic;
use App\Application\Search\SearchSection;
use App\Http\Paging\OpaqueCursor;

/**
 * The opaque paging cursor of the search endpoint (spotify-search-api, design D3). Its scope is the section and a hash
 * of the query, so a cursor only works for the section and query it was made for; the encryption is the shared
 * OpaqueCursor (spotify-library-tracks, design D3).
 */
final class SearchCursor
{
    public static function encode(SearchSection $section, string $query, int $offset): string
    {
        return OpaqueCursor::encode(self::scope($section, $query), $offset);
    }

    /**
     * The cursor's offset, or null when it does not decrypt, belongs to another section or query, or carries an
     * offset outside 0 to 1000.
     */
    public static function decode(string $cursor, SearchSection $section, string $query): ?int
    {
        return OpaqueCursor::decode($cursor, self::scope($section, $query), SearchMusic::MAX_OFFSET);
    }

    private static function scope(SearchSection $section, string $query): string
    {
        return 'search:'.$section->value.':'.hash('sha256', $query);
    }
}
