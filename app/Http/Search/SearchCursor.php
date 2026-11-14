<?php

namespace App\Http\Search;

use App\Application\Search\Queries\SearchMusic;
use App\Application\Search\SearchSection;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * The opaque paging cursor of the search endpoint (spotify-search-api, design D3). It encrypts the section, a hash of
 * the query and the offset with the app key, so the browser can neither read nor alter it, and a cursor only works
 * for the section and query it was made for.
 */
final class SearchCursor
{
    public static function encode(SearchSection $section, string $query, int $offset): string
    {
        return Crypt::encryptString(json_encode([
            's' => $section->value,
            'q' => hash('sha256', $query),
            'o' => $offset,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * The cursor's offset, or null when it does not decrypt, belongs to another section or query, or carries an
     * offset outside 0 to 1000.
     */
    public static function decode(string $cursor, SearchSection $section, string $query): ?int
    {
        try {
            $payload = json_decode(Crypt::decryptString($cursor), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($payload)
            || ($payload['s'] ?? null) !== $section->value
            || ! is_string($payload['q'] ?? null)
            || ! hash_equals(hash('sha256', $query), $payload['q'])
            || ! is_int($payload['o'] ?? null)
            || $payload['o'] < 0
            || $payload['o'] > SearchMusic::MAX_OFFSET) {
            return null;
        }

        return $payload['o'];
    }
}
