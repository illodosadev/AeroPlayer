<?php

// spotify-library-tracks, task 1.1: the library cursor scopes and the 100 000 maximum (design D3).

use App\Application\Search\SearchSection;
use App\Http\Paging\LibraryCursor;
use App\Http\Search\SearchCursor;

test('a playlist cursor round-trips, up to an offset of 100000', function (int $offset) {
    $scope = LibraryCursor::playlistScope('3cEYpjA9oz9GiPac4AsH4n');

    expect(LibraryCursor::decode(LibraryCursor::encode($scope, $offset), $scope))->toBe($offset);
})->with([0, 50, 9950, 100000]);

test('a cursor above 100000 or below 0 is rejected', function (int $offset) {
    $cursor = LibraryCursor::encode(LibraryCursor::LIKED_SCOPE, $offset);

    expect(LibraryCursor::decode($cursor, LibraryCursor::LIKED_SCOPE))->toBeNull();
})->with([100001, -50]);

test('a cursor only works for its own list', function () {
    $playlist = LibraryCursor::encode(LibraryCursor::playlistScope('3cEYpjA9oz9GiPac4AsH4n'), 50);
    $liked = LibraryCursor::encode(LibraryCursor::LIKED_SCOPE, 50);

    expect(LibraryCursor::decode($playlist, LibraryCursor::LIKED_SCOPE))->toBeNull()
        ->and(LibraryCursor::decode($playlist, LibraryCursor::playlistScope('7dKbC1VnGzXSFqY2cVvRmb')))->toBeNull()
        ->and(LibraryCursor::decode($liked, LibraryCursor::playlistScope('3cEYpjA9oz9GiPac4AsH4n')))->toBeNull()
        ->and(LibraryCursor::decode('not-a-cursor', LibraryCursor::LIKED_SCOPE))->toBeNull();
});

test('search and library cursors never read each other', function () {
    $search = SearchCursor::encode(SearchSection::Songs, 'Daft Punk', 10);
    $liked = LibraryCursor::encode(LibraryCursor::LIKED_SCOPE, 10);

    expect(LibraryCursor::decode($search, LibraryCursor::LIKED_SCOPE))->toBeNull()
        ->and(SearchCursor::decode($liked, SearchSection::Songs, 'Daft Punk'))->toBeNull();
});
