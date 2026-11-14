<?php

namespace App\Application\Library;

/**
 * Where the library overview is kept (spotify-library-overview, design D3): one entry per Spotify connection, so a new
 * connection never reads an earlier one's data.
 */
final class LibraryCache
{
    /**
     * 10 minutes.
     */
    public const SECONDS = 600;

    public static function key(string $connectionId): string
    {
        return "library:spotify:{$connectionId}";
    }
}
