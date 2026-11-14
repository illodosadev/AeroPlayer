<?php

namespace App\Spotify;

use App\Application\Search\Results\AlbumResult;
use App\Application\Search\Results\ArtistResult;
use App\Application\Search\Results\SongResult;

/**
 * Turns Spotify's track, album and artist objects into the app's common items (spotify-search-api, design D2). It is
 * the only place that knows Spotify's catalog shape. Spotify sometimes puts null entries in "items"; they are skipped.
 */
final class SpotifyCatalogMapper
{
    public const SOURCE = 'spotify';

    /**
     * @param  array<mixed>  $items  Spotify's track objects
     * @return list<SongResult>
     */
    public function songs(array $items): array
    {
        return array_map(fn (array $track) => new SongResult(
            source: self::SOURCE,
            id: $this->string($track, 'id'),
            uri: $this->string($track, 'uri'),
            title: $this->string($track, 'name'),
            artists: $this->artistNames($track),
            album: is_array($track['album'] ?? null) && is_string($track['album']['name'] ?? null) ? $track['album']['name'] : null,
            durationMs: is_int($track['duration_ms'] ?? null) ? $track['duration_ms'] : 0,
            image: $this->firstImage(is_array($track['album'] ?? null) ? $track['album'] : []),
            playable: ($track['is_playable'] ?? null) !== false,
        ), $this->objects($items));
    }

    /**
     * @param  array<mixed>  $items  Spotify's simplified album objects
     * @return list<AlbumResult>
     */
    public function albums(array $items): array
    {
        return array_map(fn (array $album) => new AlbumResult(
            source: self::SOURCE,
            id: $this->string($album, 'id'),
            uri: $this->string($album, 'uri'),
            title: $this->string($album, 'name'),
            artists: $this->artistNames($album),
            year: $this->year($album['release_date'] ?? null),
            image: $this->firstImage($album),
        ), $this->objects($items));
    }

    /**
     * @param  array<mixed>  $items  Spotify's artist objects
     * @return list<ArtistResult>
     */
    public function artists(array $items): array
    {
        return array_map(fn (array $artist) => new ArtistResult(
            source: self::SOURCE,
            id: $this->string($artist, 'id'),
            uri: $this->string($artist, 'uri'),
            title: $this->string($artist, 'name'),
            image: $this->firstImage($artist),
        ), $this->objects($items));
    }

    /**
     * The entries that are objects, without the nulls.
     *
     * @param  array<mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function objects(array $items): array
    {
        return array_values(array_filter($items, 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function string(array $object, string $key): string
    {
        return is_string($object[$key] ?? null) ? $object[$key] : '';
    }

    /**
     * @param  array<string, mixed>  $object
     * @return list<string>
     */
    private function artistNames(array $object): array
    {
        $artists = is_array($object['artists'] ?? null) ? $object['artists'] : [];

        return array_values(array_map(
            fn (array $artist) => $this->string($artist, 'name'),
            $this->objects($artists),
        ));
    }

    /**
     * The URL of the first image, Spotify's widest, or null.
     *
     * @param  array<string, mixed>  $object
     */
    private function firstImage(array $object): ?string
    {
        $url = is_array($object['images'] ?? null) ? ($object['images'][0]['url'] ?? null) : null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * The first four digits of a release date ("2001-03-12", "2001-03" or "2001"), or null.
     */
    private function year(mixed $releaseDate): ?int
    {
        return is_string($releaseDate) && preg_match('/^(\d{4})/', $releaseDate, $match) === 1 ? (int) $match[1] : null;
    }
}
