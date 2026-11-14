<?php

// spotify-search-api, task 2.1: the cursor round-trips, is opaque and is rejected when altered, made for another
// section or query, or carrying an offset above 1000 (design D3).

use App\Application\Search\SearchSection;
use App\Http\Search\SearchCursor;

/**
 * The cursor with one character in its middle changed.
 */
function alteredCursor(string $cursor): string
{
    $middle = intdiv(strlen($cursor), 2);

    return substr_replace($cursor, $cursor[$middle] === 'A' ? 'B' : 'A', $middle, 1);
}

test('a cursor round-trips', function (int $offset) {
    $cursor = SearchCursor::encode(SearchSection::Songs, 'Daft Punk', $offset);

    expect(SearchCursor::decode($cursor, SearchSection::Songs, 'Daft Punk'))->toBe($offset);
})->with([0, 10, 990, 1000]);

test('a cursor is opaque', function () {
    $cursor = SearchCursor::encode(SearchSection::Albums, 'Daft Punk', 470);
    $decoded = (string) base64_decode($cursor, true);

    expect($cursor)->not->toContain('470')
        ->and($cursor)->not->toContain('albums')
        ->and($cursor)->not->toContain('Daft')
        ->and($decoded)->not->toContain('470')
        ->and($decoded)->not->toContain('albums');
});

test('an altered cursor is rejected', function () {
    $cursor = SearchCursor::encode(SearchSection::Songs, 'Daft Punk', 10);

    expect(SearchCursor::decode(alteredCursor($cursor), SearchSection::Songs, 'Daft Punk'))->toBeNull()
        ->and(SearchCursor::decode('not-a-cursor', SearchSection::Songs, 'Daft Punk'))->toBeNull()
        ->and(SearchCursor::decode('', SearchSection::Songs, 'Daft Punk'))->toBeNull();
});

test('a cursor from another section is rejected', function () {
    $cursor = SearchCursor::encode(SearchSection::Songs, 'Daft Punk', 10);

    expect(SearchCursor::decode($cursor, SearchSection::Albums, 'Daft Punk'))->toBeNull()
        ->and(SearchCursor::decode($cursor, SearchSection::Artists, 'Daft Punk'))->toBeNull();
});

test('a cursor from another query is rejected', function () {
    $cursor = SearchCursor::encode(SearchSection::Songs, 'Justice', 10);

    expect(SearchCursor::decode($cursor, SearchSection::Songs, 'Daft Punk'))->toBeNull();
});

test('a cursor with an offset outside 0 to 1000 is rejected', function (int $offset) {
    $cursor = SearchCursor::encode(SearchSection::Songs, 'Daft Punk', $offset);

    expect(SearchCursor::decode($cursor, SearchSection::Songs, 'Daft Punk'))->toBeNull();
})->with([1001, 1010, -10]);
