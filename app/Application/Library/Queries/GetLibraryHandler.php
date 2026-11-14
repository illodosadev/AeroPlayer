<?php

namespace App\Application\Library\Queries;

use App\Application\Library\LibraryCache;
use App\Application\Library\Results\LibraryOverview;
use App\Application\Library\Results\PlaylistResult;
use App\Application\Spotify\Exceptions\SpotifyApiFailed;
use App\Application\Spotify\Exceptions\SpotifyNotConnected;
use App\Application\Spotify\Exceptions\SpotifyReconnectRequired;
use App\Application\Spotify\Exceptions\SpotifyUnavailable;
use App\Enums\SpotifyConnectionStatus;
use App\Repositories\SpotifyConnectionRepository;
use App\Spotify\SpotifyCatalogMapper;
use App\Spotify\SpotifyClient;
use App\Spotify\SpotifyScopes;
use Illuminate\Support\Facades\Cache;

final readonly class GetLibraryHandler
{
    /**
     * Playlists per request: the most Spotify allows.
     */
    private const PAGE_SIZE = 50;

    /**
     * At most 20 requests, so 1000 playlists.
     */
    private const MAX_PAGES = 20;

    public function __construct(
        private SpotifyClient $spotify,
        private SpotifyCatalogMapper $mapper,
        private SpotifyConnectionRepository $connections,
    ) {}

    /**
     * Steps 1 to 6 of design D1.
     *
     * @throws SpotifyApiFailed
     */
    public function handle(GetLibrary $query): LibraryOverview
    {
        // 1. A missing or revoked connection fails before the cache, so it never shows kept data.
        $connection = $this->connections->current() ?? throw new SpotifyNotConnected;

        if ($connection->status === SpotifyConnectionStatus::ReconnectRequired) {
            throw new SpotifyReconnectRequired;
        }

        // 2. The permissions decide which parts are read.
        $playlistsAllowed = SpotifyScopes::grants($connection->scopes, 'playlist-read-private');
        $likedAllowed = SpotifyScopes::grants($connection->scopes, 'user-library-read');

        // 3. The kept library of this connection.
        $key = LibraryCache::key($connection->connection_id);
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $this->overview($cached, $playlistsAllowed, $likedAllowed);
        }

        // 4 and 5. Spotify, only for the allowed parts.
        $data = [
            'playlists' => $playlistsAllowed ? $this->playlists($connection->spotify_user_id) : null,
            'liked_total' => $likedAllowed ? $this->likedTotal() : null,
        ];

        // 6. Keep it for 10 minutes.
        Cache::put($key, $data, LibraryCache::SECONDS);

        return $this->overview($data, $playlistsAllowed, $likedAllowed);
    }

    /**
     * Every playlist, 50 per request, until Spotify has no next page or after 1000.
     *
     * @return list<array<string, mixed>>
     */
    private function playlists(string $ownerSpotifyId): array
    {
        $playlists = [];

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = $this->spotify->get('/me/playlists', ['limit' => self::PAGE_SIZE, 'offset' => $page * self::PAGE_SIZE]);

            if (! $response->successful()) {
                throw new SpotifyUnavailable;
            }

            $items = $response->json('items');

            foreach ($this->mapper->playlists(is_array($items) ? $items : [], $ownerSpotifyId) as $playlist) {
                $playlists[] = $playlist->toArray();
            }

            if ($response->json('next') === null) {
                break;
            }
        }

        return $playlists;
    }

    /**
     * The saved-song count, from one request for a single song.
     */
    private function likedTotal(): int
    {
        $response = $this->spotify->get('/me/tracks', ['limit' => 1]);
        $total = $response->json('total');

        if (! $response->successful() || ! is_int($total)) {
            throw new SpotifyUnavailable;
        }

        return $total;
    }

    /**
     * @param  array{playlists: list<array<string, mixed>>|null, liked_total: int|null}  $data
     */
    private function overview(array $data, bool $playlistsAllowed, bool $likedAllowed): LibraryOverview
    {
        return new LibraryOverview(
            playlists: $playlistsAllowed && is_array($data['playlists'] ?? null)
                ? array_map(fn (array $playlist) => PlaylistResult::fromArray($playlist), $data['playlists'])
                : null,
            playlistsAllowed: $playlistsAllowed,
            likedTotal: $likedAllowed ? ($data['liked_total'] ?? null) : null,
            likedAllowed: $likedAllowed,
        );
    }
}
