<?php

namespace App\Application\Search;

/**
 * A section of the search results (spotify-search-api, design D1). The value is the section's name in the app's
 * JSON; each section maps to one Spotify search type.
 */
enum SearchSection: string
{
    case Songs = 'songs';
    case Albums = 'albums';
    case Artists = 'artists';

    /**
     * The value of Spotify's "type" parameter for this section.
     */
    public function spotifyType(): string
    {
        return match ($this) {
            self::Songs => 'track',
            self::Albums => 'album',
            self::Artists => 'artist',
        };
    }

    /**
     * The key of this section's paging object in Spotify's answer.
     */
    public function spotifyKey(): string
    {
        return $this->spotifyType().'s';
    }
}
