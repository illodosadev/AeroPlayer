<?php

namespace App\Application\Library;

use App\Application\Library\Exceptions\PlaylistUnavailable;
use App\Application\Library\Results\LikedSongResult;
use App\Application\Library\Results\PlaylistResult;
use App\Application\Library\Results\PlaylistSongResult;
use App\Application\Library\Results\SongPage;
use App\Application\Spotify\Exceptions\SpotifyApiFailed;
use App\Application\Spotify\Exceptions\SpotifyUnavailable;
use App\Spotify\SpotifyCatalogMapper;
use App\Spotify\SpotifyClient;
use Illuminate\Http\Client\Response;

/**
 * The Spotify calls shared by the playlist and Liked Songs handlers (spotify-library-tracks, design D1). It never
 * reads the connection: the handlers do, because repositories belong in handlers.
 */
final readonly class SpotifyLibraryReader
{
    /**
     * Songs per request: the most Spotify allows.
     */
    public const PAGE_SIZE = 50;

    private const PLAYLIST_FIELDS = 'id,uri,name,images,owner(id,display_name),collaborative,items(total)';

    public function __construct(
        private SpotifyClient $spotify,
        private SpotifyCatalogMapper $mapper,
    ) {}

    /**
     * @throws PlaylistUnavailable
     * @throws SpotifyApiFailed
     */
    public function playlist(string $id, string $spotifyUserId): PlaylistResult
    {
        $response = $this->spotify->get('/playlists/'.rawurlencode($id), ['fields' => self::PLAYLIST_FIELDS]);

        $this->ensurePlaylistAnswer($response);

        $body = $response->json();
        $playlists = $this->mapper->playlists(is_array($body) ? [$body] : [], $spotifyUserId);

        return $playlists[0] ?? throw new SpotifyUnavailable;
    }

    /**
     * @throws PlaylistUnavailable
     * @throws SpotifyApiFailed
     */
    public function playlistSongs(string $id, int $offset): SongPage
    {
        $response = $this->spotify->get('/playlists/'.rawurlencode($id).'/items', [
            'limit' => self::PAGE_SIZE,
            'offset' => $offset,
            'market' => 'from_token',
        ]);

        $this->ensurePlaylistAnswer($response);

        $entries = $response->json('items');

        return $this->page($response, $this->mapper->playlistSongs(is_array($entries) ? $entries : [], $offset), $offset);
    }

    /**
     * @throws SpotifyApiFailed
     */
    public function likedSongs(int $offset): SongPage
    {
        $response = $this->spotify->get('/me/tracks', [
            'limit' => self::PAGE_SIZE,
            'offset' => $offset,
            'market' => 'from_token',
        ]);

        if (! $response->successful()) {
            throw new SpotifyUnavailable;
        }

        $entries = $response->json('items');

        return $this->page($response, $this->mapper->likedSongs(is_array($entries) ? $entries : []), $offset);
    }

    /**
     * 404 and 403 mean the playlist is gone or forbidden; any other refusal is a failure.
     */
    private function ensurePlaylistAnswer(Response $response): void
    {
        if ($response->status() === 404 || $response->status() === 403) {
            throw new PlaylistUnavailable;
        }

        if (! $response->successful()) {
            throw new SpotifyUnavailable;
        }
    }

    /**
     * @param  list<PlaylistSongResult|LikedSongResult>  $items
     */
    private function page(Response $response, array $items, int $offset): SongPage
    {
        $total = $response->json('total');

        return new SongPage(
            items: $items,
            total: is_int($total) ? $total : null,
            nextOffset: $response->json('next') !== null ? $offset + self::PAGE_SIZE : null,
        );
    }
}
