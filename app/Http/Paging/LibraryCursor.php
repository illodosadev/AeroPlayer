<?php

namespace App\Http\Paging;

/**
 * The scopes of the library's song cursors (spotify-library-tracks, design D3): one per playlist, and one for Liked
 * Songs. A playlist can hold up to 10 000 songs, so the cursors allow offsets up to 100 000.
 */
final class LibraryCursor
{
    public const MAX_OFFSET = 100000;

    public static function playlistScope(string $playlistId): string
    {
        return "library:playlist:{$playlistId}";
    }

    public const LIKED_SCOPE = 'library:liked';

    public static function encode(string $scope, int $offset): string
    {
        return OpaqueCursor::encode($scope, $offset);
    }

    public static function decode(string $cursor, string $scope): ?int
    {
        return OpaqueCursor::decode($cursor, $scope, self::MAX_OFFSET);
    }
}
