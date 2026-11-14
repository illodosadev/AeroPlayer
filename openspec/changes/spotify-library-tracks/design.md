# Design

## Context

See proposal.md, "Why". The requirements are in `specs/music-library/spec.md`; T-08's research (endpoints, February 2026 limits, playing rules) is in `backlog/tasks/T-08-biblioteca-spotify.md`.

What exists:

- **`spotify-library-overview`:**
  - the `Library` context;
  - `PlaylistResult` and `SpotifyCatalogMapper::playlists($items, $ownerSpotifyId)`;
  - `/library` and its state handling in `ShowLibraryController`.

  The spike of 2026-11-14 confirmed that a followed playlist plays as a context and that saved songs play by their uris.
- **`spotify-search-api` / `spotify-search-page`:**
  - `SongResult` and `SpotifyCatalogMapper::songs()`;
  - `SearchCursor`, an encrypted cursor bound to section and query;
  - the song row inside `Search.vue`;
  - the paging guard of `useMusicSearch` (one request while loading, dedupe).
- **`useSpotifyPlayer().play({contextUri | uris, offset})`** throws when the device is not ready, and `PlayerRequestError` when the endpoint fails.
- **The Spotify API:**
  - `GET /playlists/{id}` takes `fields`;
  - `GET /playlists/{id}/items` takes `limit` up to 50 and `market`. Each entry has `is_local` and `item`, which is null for a deleted song and may be an episode. It answers 403 for playlists the user neither owns nor collaborates on;
  - `GET /me/tracks` takes `limit` up to 50 and `market`; each entry has `added_at` and `track`.

## Goals / Non-Goals

**Goals:**

- One song row and one paging approach for search, playlists and Liked Songs.
- The playlist position sent to Spotify is always Spotify's own index, even when entries are hidden.

**Non-Goals:**

- Caching song pages, by the owner's choice: one request per 50 songs.
- The app's queue (T-13): a page of saved songs stops at its end.

## Decisions

### D1. Three queries over one reader

| Piece | Where |
|---|---|
| `GetPlaylist(string $id)` → `PlaylistPage(PlaylistResult $playlist, ?SongPage $songs)` | `app/Application/Library/Queries` |
| `GetPlaylistSongs(string $id, int $offset)` → `SongPage` | `app/Application/Library/Queries` |
| `GetLikedSongs(int $offset)` → `LikedSongsPage(bool $allowed, ?SongPage $songs)` | `app/Application/Library/Queries` |
| `SongPage(list $items, ?int $total, ?int $nextOffset)`, `PlaylistSongResult` and `LikedSongResult` | `app/Application/Library/Results` |
| `PlaylistUnavailable` (extends `UseCaseFailed`) | `app/Application/Library/Exceptions` |
| `SpotifyLibraryReader`, the Spotify calls shared by the three handlers | `app/Application/Library` |

`SpotifyLibraryReader` injects only `SpotifyClient` and `SpotifyCatalogMapper`. It never reads the connection: the house rule keeps repositories in handlers (AGENTS.md, "Repositories for all data access").

- **`playlist($id, $spotifyUserId)`** sends `GET /playlists/{id}` with `fields=id,uri,name,images,owner(id,display_name),collaborative,items(total)`.
  - 404 or 403 throws `PlaylistUnavailable`; any other non-2xx throws `SpotifyUnavailable`.
  - It maps the result with `SpotifyCatalogMapper::playlists([$body], $spotifyUserId)[0]`.
- **`playlistSongs($id, $offset)`** sends `GET /playlists/{id}/items` with `limit=50`, the offset and `market=from_token`, with the same 404/403 rule.
- **`likedSongs($offset)`** sends `GET /me/tracks` with `limit=50`, the offset and `market=from_token`.
- **The next offset** is `offset + 50` when Spotify's `next` is not null, otherwise null. The total is Spotify's `total`.

The handlers inject `SpotifyConnectionRepository` and the reader:

- **Each handler** first reads `current()`. No connection throws `SpotifyNotConnected`, and `reconnect_required` throws `SpotifyReconnectRequired`, as in `GetLibraryHandler`. It then passes `spotify_user_id` to the reader where needed.
- **`GetPlaylistHandler`** reads the playlist, then its first song page only when `can_list_tracks` is true.
- **`GetLikedSongsHandler`** returns `allowed: false` with no request when `user-library-read` is not granted.

*Alternative:* `GetPlaylist` asks `GetPlaylistSongs` through the bus. Rejected: handlers calling the bus hide the flow. The reader keeps the calls shared and plain.

### D2. Song mapping

- **`SpotifyCatalogMapper::playlistSongs(array $entries, int $offset)`:**
  - entry `i` has `position = offset + i`, counted before anything is skipped;
  - an entry whose `item` is not an array, or whose `item.type` is not `track`, is skipped;
  - `local` is `is_local === true`, and `playable` is false for a local file, otherwise `songs()`'s `playable`.
- **`SpotifyCatalogMapper::likedSongs(array $entries)`** maps `track` with `songs()`, adds `added_at`, and skips entries without a track.
- **`PlaylistSongResult(SongResult $song, int $position, bool $local)`** and **`LikedSongResult(SongResult $song, string $addedAt)`** have `toArray()` = the song's keys followed by their extra keys. The key order matches the spec.

### D3. A shared opaque cursor

- **`App\Http\Paging\OpaqueCursor`** has:
  - `encode(string $scope, int $offset)`;
  - `decode(string $cursor, string $scope, int $maxOffset): ?int`.

  It encrypts `{s, o}` with `Crypt`. `decode` returns null for a cursor that does not decrypt, has another scope, or has an offset outside 0 to the maximum.
- **`SearchCursor`** keeps its API and tests, and delegates with the scope `search:{section}:{sha256(query)}` and a maximum of 1000.
- **The library scopes** are `library:playlist:{id}` and `library:liked`, with a maximum of 100000.

### D4. Routes and controllers

| Route | Name | Controller | Answer |
|---|---|---|---|
| `GET library/playlists/spotify/{id}` | `library.playlist` | `ShowPlaylistController` | Inertia `library/Playlist` |
| `GET library/playlists/spotify/{id}/songs?cursor=` | `library.playlist.songs` | `ShowPlaylistSongsPageController` | JSON `{items, next_cursor}` |
| `GET library/liked` | `library.liked` | `ShowLikedSongsController` | Inertia `library/Liked` |
| `GET library/liked/songs?cursor=` | `library.liked.songs` | `ShowLikedSongsPageController` | JSON `{items, next_cursor}` |

- `{id}` is constrained to `[A-Za-z0-9]{1,64}`, so an id that cannot be Spotify's is a plain 404.
- **The pages** always answer 200. Their props:
  - `library/Playlist`: `playlist`, `songs` (list or null), `nextCursor` and `error`;
  - `library/Liked`: `allowed`, `songs`, `total`, `nextCursor` and `error`.

  `error` follows `ShowLibraryController`'s mapping, plus `playlist_unavailable` for `PlaylistUnavailable`. That mapping moves to a shared trait, `Library/Concerns/MapsLibraryErrors`. `tests/Unit/ArchitectureTest.php` gets `App\Http\Controllers\Library\Concerns` in `CONTROLLER_RULE_EXEMPT`, as `Spotify\Concerns` already is, so the trait is not checked as a controller.
- **The JSON endpoints:**
  - `LibrarySongsPageRequest` requires `cursor` and decodes it with the route's scope in an `after` hook. A bad cursor answers 422 before any Spotify request;
  - failures go through `RespondsWithSpotifyErrors`, plus 404 `playlist_unavailable`;
  - every answer carries `no-store`;
  - `bootstrap/app.php` answers the JSON 401 for `library/playlists/spotify/*/songs` and `library/liked/songs` too.

### D5. Frontend

- **`resources/js/components/SongRow.vue`**, extracted from `Search.vue`:
  - it takes a song (common keys, plus optional `local`) and emits `play`;
  - a song that cannot play is a `disabled` button, dimmed, with "Local file, can't play here" or "Not available";
  - its hooks are the ones the search tests use (`data-part="song-row"`, `data-id`, `data-playable`, `data-part` for the parts), so `Search.vue` switches to it unchanged.
- **`resources/js/composables/usePagedSongs.ts`:**
  - it takes the first page, its cursor, the endpoint and a key function (`position` for a playlist, since a song may repeat; `id` for Liked Songs);
  - it keeps `pages` (the songs each loaded page adds, after dropping keys already shown) and `items` (all of them, in order), plus `nextCursor`, `loading` and `failed`. A page therefore never holds a song that is not on screen;
  - `loadMore()` returns at once while loading; 401 sends to `/login`; 422 reloads the page (`router.reload()`); any other failure sets `failed`.
- **`library/Playlist.vue`:** the header (an `AeroCover` at 96 px, the title, "by {owner}", "{n} songs"), "Play", the songs and "Load more".
  - "Play" plays `{contextUri, offset: {position: 0}}`, or `{contextUri}` alone for a followed playlist. `play()` and the start endpoint default a missing offset to `{position: 0}` (`spotify-playback`), so Spotify receives position 0 either way; the spike confirmed a followed playlist plays like that. It is disabled for an empty playlist.
  - A song plays `{contextUri, offset: {position: song.position}}`.
- **`library/Liked.vue`:** the header, the songs and "Load more". A song plays `{uris, offset: {position}}`. `uris` are the playable songs that its loaded page shows, after dedupe, and `position` is its index among them.
- **Both pages** reuse the Aero layout of `Library.vue` (the "Back" link to `/library`), its states, and the generic "Playback couldn't start" message from `Search.vue`.
- **`Library.vue`:** playlist rows and the "Liked Songs" entry become `Link`s with the same content.

### D6. Tests

- **Unit, `tests/Unit/Library/SpotifySongMapperTest.php`:**
  - positions with skipped entries and the offset;
  - local files, episodes and null items;
  - `added_at`;
  - the exact keys.
- **Unit:** `OpaqueCursor` is covered through the existing `SearchCursorTest`, plus `tests/Feature/Library/LibraryCursorTest.php` for the scopes and the 100000 maximum.
- **Feature, `tests/Feature/Library/PlaylistPageTest.php` and `LikedSongsPageTest.php`:**
  - the props and their keys;
  - the requests, with `limit`, `offset`, `market=from_token` and `fields`;
  - the JSON pages, 422 cursors and the JSON 401;
  - the states, 404 and 403, followed playlists with no items request, and deleted entries.
- **Browser, `tests/Browser/PlaylistPageTest.php` and `LikedSongsPageTest.php`:**
  - opening from `/library`, no `data-slot`, the focus walk and 375 px;
  - "Load more", a double click and a failure;
  - every playing scenario with the fake SDK. The start request is checked on the server, and disabled rows are pressed with a script.
- **Regression:** the existing search unit, feature and browser tests must pass unchanged after the row and cursor extraction.
- **Fixtures:** `tests/Fixtures/spotify/playlist.json`, `playlist-items.json` (with a local file, an unplayable song and a null item) and `me-tracks.json`.

### D7. Manual checks

After the automated steps, the owner runs T-08's three `@manual` scenarios with the real account and records the date and result in `tasks.md`:

- an own playlist plays and advances;
- a followed playlist plays entirely;
- a saved song plays and advances.

## Risks / Trade-offs

- [Extracting the song row could break the search page] → The row keeps the search hooks, and the search browser tests run unchanged.
- [Changing `SearchCursor`'s payload invalidates cursors in flight] → They are short-lived. A stale one gets a 422, and the search page already restarts the search.
- [A playlist changed in Spotify while paging can repeat or skip a song] → Accepted, as T-08 says. Reopening the page starts again.
- [Saved songs stop at the end of their page of 50] → Accepted until T-13's queue.

## Migration Plan

No schema change. Archive `spotify-library-overview` before this change, since this delta adds to its `music-library` capability. Deploy as usual. Rollback means reverting the commits.

## Backlog follow-ups (applied at /ship)

- **T-08:** `status: done`, recording the manual checks.
- **T-13:** `usePagedSongs` and the readers' next offsets are what `POST /queue/continue` can reuse; saved songs stop at their page until then.
- **T-14:** "Add to playlist" goes in `SongRow.vue`, hidden for local files and unavailable songs.
- **T-24:** reuse `SongRow.vue` and `usePagedSongs` for album pages.
- **T-12 and T-22:** the routes keep the service segment (`spotify`) for `youtube-music`.
