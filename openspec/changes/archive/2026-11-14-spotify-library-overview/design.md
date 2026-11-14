# Design

## Context

See proposal.md, "Why". The requirements are in `specs/music-library/spec.md`; T-08's full research (endpoints, February 2026 limits, scopes) is in `backlog/tasks/T-08-biblioteca-spotify.md`.

What exists:

- **`App\Spotify\SpotifyClient`** (spec `spotify-web-api`):
  - `get($path, $query)` refreshes tokens and keeps the shared rate-limit pause;
  - it throws `SpotifyNotConnected`, `SpotifyReconnectRequired`, `SpotifyUnavailable` or `SpotifyRateLimited`;
  - it returns any other answer, including 403 and 404.
- **`SpotifyConnectionRepository::current()`** gives the connection, with:
  - `connection_id`, a new uuid on every connection;
  - `spotify_user_id`;
  - `scopes`, the granted scopes separated by spaces;
  - `status`.

  `SpotifyScopes::REQUIRED` already includes `playlist-read-private`, `playlist-read-collaborative` and `user-library-read`.
- **`SpotifyCatalogMapper`** turns tracks, albums and artists into the common items (`spotify-search-api`).
- **The Aero pages:** `Search.vue` and `settings/Spotify.vue` are the model: `AeroBackground`, a `main` with a "Back" link, an `AeroGlassPanel`, and a connect button that is a GET form to `/spotify/connect`.
- **The Spotify API:**
  - `GET /me/playlists` takes `limit` up to 50;
  - each playlist carries `owner {id, display_name}`, `collaborative`, `images`, and `items {href, total}` only for playlists the user owns or collaborates on;
  - `GET /me/tracks?limit=1` gives the saved-song `total`.

## Goals / Non-Goals

**Goals:**

- A `Library` context independent of the service, so T-12 adds YouTube Music in the same handler and page.
- One cold load reads Spotify; reloads within 10 minutes read nothing.

**Non-Goals:**

- The playlist and Liked Songs pages, "Load more" and playing (`spotify-library-tracks`).
- `/library` as the home page (T-25), "My playlists" (T-14) and mixing services (T-22).

## Decisions

### D0. The spike comes first

Before any code, the owner checks with the real account, from the browser console of any signed-in page with the player ready. `window.aeroPlayer` is the player's test hook from `spotify-web-player`.

1. `await window.aeroPlayer.play({contextUri: 'spotify:playlist:<id of a playlist the owner only follows>'})`, then "Next" in the bar: the playlist must sound and advance.
2. `await window.aeroPlayer.play({uris: [<three saved-song uris>], offset: {position: 1}})`, then "Next": the second and then the third song must sound.

The result and the date go into `tasks.md`, without ids. If step 1 fails, implementation stops and T-08 is revised. Step 2 only affects `spotify-library-tracks`, and a failure there is recorded for that change.

### D1. A `Library` context with one query and one command

| Piece | Where |
|---|---|
| query `GetLibrary`, handler returns `LibraryOverview` | `app/Application/Library/Queries` |
| command `RefreshLibrary`, handler forgets the cached library | `app/Application/Library/Commands` |
| `LibraryOverview(?list $playlists, bool $playlistsAllowed, ?int $likedTotal, bool $likedAllowed)` and `PlaylistResult` | `app/Application/Library/Results` |
| `SpotifyCatalogMapper::playlists(array $items, string $ownerSpotifyId)` | `app/Spotify` |

`GetLibraryHandler`:

1. Reads `current()`. No connection throws `SpotifyNotConnected`; `reconnect_required` throws `SpotifyReconnectRequired`. Both happen before the cache, so a revoked or removed connection never shows kept data.
2. Reads the scopes: `playlist-read-private` allows the playlists, and `user-library-read` allows the saved songs. `SpotifyScopes` gets a `grants($granted, $scope)` helper.
3. Returns the cached library for this `connection_id` when there is one.
4. Otherwise, when the playlists are allowed:
   - it sends `GET /me/playlists` with `limit=50` and `offset` 0, 50, 100…, until `next` is null or after 20 requests (1000 playlists);
   - a non-2xx answer throws `SpotifyUnavailable`.
5. When the saved songs are allowed, it sends `GET /me/tracks?limit=1` and reads `total`; a non-2xx answer throws `SpotifyUnavailable`.
6. It caches the result and returns it.

A part that is not allowed is `null`, and nothing is sent for it.

Writing the cache inside a query is infrastructure, not a change the owner sees. It is the same reasoning as `GetSpotifyPlaybackToken`, which refreshes a token inside a query (`spotify-playback-api`, design D2).

Cached playlists are plain arrays. `PlaylistResult::fromArray()` rebuilds them, so the cached path and the fresh path return the same `LibraryOverview`.

*Alternative:* a `SpotifyLibrary` service class, as T-08 suggested. Rejected: the house rule puts use cases in handlers, which inject the client like `SearchMusicHandler` does.

### D2. The playlist format

| Field | Value |
|---|---|
| `source` | `"spotify"` |
| `id`, `uri` | as in Spotify |
| `title` | `name` |
| `image` | `images[0].url`, or null |
| `owner` | `owner.display_name`, or null |
| `collaborative` | `collaborative === true` |
| `can_list_tracks` | `owner.id` equals the connection's `spotify_user_id`, or `collaborative` |
| `total` | `items.total` when it is an int, else null |

- `null` entries are skipped.
- The deprecated `tracks` field is ignored, so a followed playlist without `items` has `total` null.

### D3. The cache

- **The key** is `library:spotify:{connection_id}`. The value holds plain arrays: the playlists, already mapped, and the saved-song total. It lasts 600 s in the default cache store.
- **A new connection** gets a new `connection_id`, so it never reads an earlier entry. Disconnecting deletes the connection, so step 1 of D1 throws before the cache is read.
- **During a rate-limit pause**, a cached library is still served, because the cache is read before Spotify. Without one, the client throws `SpotifyRateLimited`.
- **"Refresh"** is `POST /library/refresh` (`RefreshLibraryController`). It dispatches `RefreshLibrary`, which forgets the key of the current connection, and redirects to `/library`, which reads Spotify again.

*Alternative:* `GET /library?refresh=1`. Rejected: a GET with a side effect, which a reload of the URL would repeat.

### D4. The page

- **`ShowLibraryController`** asks `GetLibrary` and renders `Library` with status 200. Its props:
  - `playlists`: a list of arrays, or null;
  - `playlistsAllowed`;
  - `likedTotal`: an int, or null;
  - `likedAllowed`;
  - `error`.

  It catches `SpotifyApiFailed` and sets `error`:

  | Exception | `error` |
  |---|---|
  | `SpotifyNotConnected` | `spotify_not_connected` |
  | `SpotifyReconnectRequired` | `spotify_reconnect_required` |
  | `SpotifyRateLimited` | `rate_limited` |
  | anything else | `spotify_unavailable` |

  The data props are then null. No Spotify body or token reaches the props.
- **`Library.vue`**, an Aero page like `Search.vue`:
  - `main#library-page` with a "Back" link, an `AeroGlassPanel`, the title "Library", and a "Refresh" `AeroButton` (`router.post('/library/refresh')`) when there is no error;
  - the "Liked Songs" entry: a generic `AeroCover`, then "{n} songs" or the permission text;
  - the "Playlists" `h2` and `ul`: each row has an `AeroCover` (`:size="48"`), the title, then "by {owner}" when `can_list_tracks` is false and the owner is known, otherwise "{n} songs" when `total` is not null; a "Collaborative" mark, and the "Spotify" label.
- **Counts** use `toLocaleString('en-US')`, so 3200 reads "3,200 songs"; 1 reads "1 song".
- **The states, in order of precedence** (the first that applies wins):

  | # | State | Shows |
  |---|---|---|
  | 1 | `spotify_not_connected` | the connect text and a GET form "Connect Spotify" |
  | 1 | `spotify_reconnect_required` | the revoked text and "Reconnect Spotify" |
  | 1 | `rate_limited` | the busy text |
  | 1 | `spotify_unavailable` | the failure text and "Try again" (`router.reload()`) |
  | 2 | `playlistsAllowed` false | the permission text and "Reconnect Spotify" in place of the list; the "Liked Songs" entry as usual |
  | 2 | `likedAllowed` false | the "Liked Songs" entry with the permission text and no number; the list as usual |
  | 3 | both allowed, no playlists and `likedTotal` 0 | "Your library is empty." only |
  | 3 | both allowed, no playlists and a saved-song count | the entry and "No playlists yet." |

  The empty states apply only when both parts were read, so a missing permission never reads as an empty library.

- **Rows are not links in this change.** `spotify-library-tracks` makes them open the playlist and Liked Songs pages.
- **`AppSidebar.vue`** gets `{title: 'Library', href: '/library', icon: Library}` after "Search".

### D5. Tests

- **Unit, `tests/Unit/Library/SpotifyPlaylistMapperTest.php`:** the mapping table of D2, null entries, a followed playlist without `items`, and the exact keys.
- **Feature, `tests/Feature/Library/LibraryPageTest.php`:**
  - `assertInertia` on the props and their exact keys;
  - `Http::assertSent` and the recorded requests for the limits and offsets;
  - `$this->travel(11)->minutes()` for expiry;
  - a new connection from the factory, with another `connection_id`, for the reconnect case;
  - `SpotifyClient::PAUSE_KEY` in the cache for the busy case;
  - `invalid_grant` on the token URL for the revoked case;
  - one test per state.
- **Browser, `tests/Browser/LibraryPageTest.php`:**
  - opening from the sidebar, and no `data-slot`;
  - the focus walk from `#library-page` and the 375 px check;
  - the texts "by Alice", "Collaborative", "12 songs" and "3,200 songs", and the generic cover;
  - "Refresh" and "Try again" end to end.

  Helper names carry a `libraryPage` prefix.
- **Fixtures, `tests/Fixtures/spotify/`:** `me-playlists.json`, copied from Spotify's documented shape with base62 ids, holding an own playlist, a followed one without `items` and a collaborative one of another user. Helpers build the 120-playlist and 3200-song cases.

## Risks / Trade-offs

- [A cold load with 1000 playlists sends 21 requests] → It is rare and capped; the cache makes reloads free for 10 minutes.
- [A 429 in the middle of the playlist pages] → The client throws `SpotifyRateLimited`, nothing is cached, and the page shows the busy text.
- [Spotify covers expire within a day] → The cache lasts 10 minutes, far shorter.
- [Old cache entries outlive a reconnect] → They expire within 10 minutes and are never read.

## Migration Plan

No schema change. Deploy as usual. Rollback means reverting the commit.

## Backlog follow-ups (applied at /ship)

- **T-08:** stays `enriched`, with `change: spotify-library-tracks` once proposed. Record the spike result.
- **T-25:** `/library` exists for the home page redirect.
- **T-14:** "My playlists" goes above the Spotify list in `Library.vue`.
- **T-12 and T-22:** YouTube Music joins `GetLibraryHandler` and the same list.
