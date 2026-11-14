<?php

namespace App\Spotify;

use App\Application\Library\Results\LikedSongResult;
use App\Application\Library\Results\PlaylistResult;
use App\Application\Library\Results\PlaylistSongResult;
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
     * Spotify's simplified playlist objects (spotify-library-overview, design D2). The deprecated "tracks" field is
     * ignored: a playlist the owner only follows comes without "items", so its total is null.
     *
     * @param  array<mixed>  $items  Spotify's simplified playlist objects
     * @param  string  $ownerSpotifyId  the Spotify id of the app's owner, to tell own playlists apart
     * @return list<PlaylistResult>
     */
    public function playlists(array $items, string $ownerSpotifyId): array
    {
        return array_map(function (array $playlist) use ($ownerSpotifyId) {
            $owner = is_array($playlist['owner'] ?? null) ? $playlist['owner'] : [];
            $collaborative = ($playlist['collaborative'] ?? false) === true;
            $displayName = $this->string($owner, 'display_name');
            $total = is_array($playlist['items'] ?? null) ? ($playlist['items']['total'] ?? null) : null;

            return new PlaylistResult(
                source: self::SOURCE,
                id: $this->string($playlist, 'id'),
                uri: $this->string($playlist, 'uri'),
                title: $this->string($playlist, 'name'),
                image: $this->firstImage($playlist),
                owner: $displayName !== '' ? $displayName : null,
                collaborative: $collaborative,
                canListTracks: $collaborative || ($ownerSpotifyId !== '' && $this->string($owner, 'id') === $ownerSpotifyId),
                total: is_int($total) ? $total : null,
            );
        }, $this->objects($items));
    }

    /**
     * A playlist's entries (spotify-library-tracks, design D2). Each entry's position is Spotify's index, counted
     * before anything is skipped; entries without a song (deleted songs) and podcast episodes are skipped. A local
     * file cannot play here.
     *
     * @param  array<mixed>  $entries  Spotify's playlist item objects
     * @param  int  $offset  the index of the first entry in the playlist
     * @return list<PlaylistSongResult>
     */
    public function playlistSongs(array $entries, int $offset): array
    {
        $songs = [];

        foreach (array_values($entries) as $index => $entry) {
            $item = is_array($entry) ? ($entry['item'] ?? $entry['track'] ?? null) : null;

            if (! is_array($item) || ($item['type'] ?? 'track') !== 'track') {
                continue;
            }

            $local = ($entry['is_local'] ?? false) === true;
            $song = $this->songs([$item])[0];

            $songs[] = new PlaylistSongResult($local ? $song->notPlayable() : $song, $offset + $index, $local);
        }

        return $songs;
    }

    /**
     * The saved songs (spotify-library-tracks, design D2), with when each was saved. Entries without a song are
     * skipped.
     *
     * @param  array<mixed>  $entries  Spotify's saved track objects
     * @return list<LikedSongResult>
     */
    public function likedSongs(array $entries): array
    {
        $songs = [];

        foreach ($this->objects($entries) as $entry) {
            if (! is_array($entry['track'] ?? null)) {
                continue;
            }

            $songs[] = new LikedSongResult($this->songs([$entry['track']])[0], $this->string($entry, 'added_at'));
        }

        return $songs;
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
