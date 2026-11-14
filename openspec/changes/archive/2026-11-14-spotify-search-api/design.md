# Design

## Context

See proposal.md, "Why". The requirements are in `specs/music-search/spec.md`.

Spotify's search, as researched in T-07:

- `GET /v1/search` takes `q`, `type` (a comma list), `limit` (at most 10 in development mode) and `offset` (0 to 1000).
- With `market=from_token`, tracks carry `is_playable`.
- Each type answers `{items, total, next, offset}`.

What exists:

- **`App\Spotify\SpotifyClient`:**
  - `get($path, $query)` returns a `Response` for anything that is not a 401, a 429 or a 5xx;
  - it throws `SpotifyNotConnected`, `SpotifyReconnectRequired`, `SpotifyUnavailable` or `SpotifyRateLimited`;
  - it refreshes tokens and keeps the shared rate-limit pause.
- **`Spotify\Concerns\RespondsWithSpotifyErrors`:** maps `SpotifyApiFailed` to JSON with `no-store`. The owner chose to reuse it here: 429 `rate_limited` with `retry_after`, and 502 `spotify_unavailable`.
- **`bootstrap/app.php`:** renders the JSON 401 for `spotify/token` and `spotify/player/*`.
- **The house rules:** CQRS (handlers in `app/Application/<Context>`, queries return `Results/` objects); single-action controllers grouped by context; every new route goes into `RoutesTest`.

## Goals / Non-Goals

**Goals:**

- A contract the search page and later services can share: items in the app's format, sections, and an opaque cursor per section.
- No Spotify field name or offset leaks to the browser.

**Non-Goals:**

- The page (`spotify-search-page`), mixing services (T-22) and caching.

## Decisions

### D1. A service-independent `Search` context

| Piece | Where |
|---|---|
| query `SearchMusic(string $query, ?SearchSection $section, int $offset)` | `app/Application/Search/Queries` |
| enum `SearchSection`: `Songs`, `Albums` and `Artists`, each mapping to a Spotify type (`track`, `album`, `artist`) | `app/Application/Search` |
| results `SearchResults` (three `SectionPage`s, or one) and `SectionPage(list $items, ?int $nextOffset)` | `app/Application/Search/Results` |
| items `SongResult`, `AlbumResult` and `ArtistResult`, `final readonly` with `toArray()` | `app/Application/Search/Results` |
| `SpotifyCatalogMapper`, which turns Spotify's track, album and artist objects into those items | `app/Spotify` |

- `SearchMusicHandler` asks Spotify through the client and the mapper. T-11 will add YouTube Music as a second source in the same handler, without touching the contract.
- A non-2xx answer that the client returns, such as a 403 from development mode, throws `SpotifyUnavailable`. T-07 left that message open, and the generic one is enough for a single owner.
- The query returns offsets, not cursors. Cursors are an HTTP concern of the controller (D3).

*Alternative:* a `Spotify` context. Rejected: T-07 requires one list independent of the service.

### D2. Mapping to the common format

**Song:**

| Field | Value |
|---|---|
| `source` | `"spotify"` |
| `id`, `uri` | as in Spotify |
| `title` | `name` |
| `artists` | `artists[].name` |
| `album` | `album.name` |
| `duration_ms` | as in Spotify |
| `image` | `album.images[0].url`, or null |
| `playable` | `is_playable !== false`, so a missing field counts as playable |

**Album:** `title` ← `name`; `artists` ← names; `year` ← the first four digits of `release_date`, as an int, or null; `image` ← `images[0].url` or null.

**Artist:** `title` ← `name`; `image` ← `images[0].url` or null.

Spotify sometimes puts `null` entries in `items`; the mapper skips them.

### D3. An encrypted cursor bound to section and query

- `App\Http\Search\SearchCursor`, an HTTP helper outside the controllers namespace, encodes `{s: section, q: sha256(query), o: offset}` with `Crypt::encryptString()`. The result is opaque, and it is authenticated, so any change fails to decrypt.
- `decode($cursor, $section, $query)` returns the offset, or null when:
  - the cursor does not decrypt;
  - its section or query hash differs;
  - its offset is not an integer from 0 to 1000.
- **Next cursor:** `nextOffset = offset + 10` when Spotify's `next` is not null and `nextOffset < 1000`; otherwise null. Only the controller turns `nextOffset` into a cursor.

*Alternative:* a base64 JSON with an HMAC. Rejected: `Crypt` already gives authenticated, opaque text with the app key and no extra code.

### D4. The endpoint

- **Route:** `GET search/results`, named `search.results`, in the `auth` group, served by `Search/ShowSearchResultsController`.
- **`SearchResultsRequest`:**
  - trims `q` in `prepareForValidation`;
  - validates `q` as `required|string|max:200`, `section` as `nullable|in:songs,albums,artists` and `cursor` as `nullable|string|required_with:section`;
  - in an `after` hook, decodes the cursor with the section and query, and adds an error when it is invalid or comes without a section.

  A JSON request gets Laravel's 422.
- **The controller** asks `SearchMusic` and answers:
  - without a section: `{songs: {items, next_cursor}, albums: {…}, artists: {…}}`;
  - with one: `{items, next_cursor}`.

  `SpotifyApiFailed` goes through `RespondsWithSpotifyErrors`. Every answer carries `no-store`.
- The trait stays where it is; this controller uses it from the `Search` context.
- **Spotify request** (with the first page's offset at 0):

  ```
  /search?q={query}&type={track,album,artist | one type}&limit=10&offset={offset}&market=from_token
  ```

- **`bootstrap/app.php`:** adds `search/results` to the paths that get the JSON 401.

### D5. Fixtures

`tests/Fixtures/spotify/` holds trimmed copies of Spotify's documented search answers, with 22-character base62 ids. The tests build the paged cases (25 tracks, `next` null) from small helpers over those fixtures, and assert the request's query with `Http::assertSent`.

## Risks / Trade-offs

- [Cursors stop working when `APP_KEY` changes] → They are short-lived paging tokens. A stale one gets a 422, and the page starts a new search.
- [Spotify changes a field name] → The mapper is the only place that knows Spotify's shape, and the fixtures pin it.
- [`retry_after` instead of T-07's 503] → This is the owner's choice for one consistent error API. The page shows the same "not available" message for 429 and 502.

## Migration Plan

No schema change. Deploy as usual. Rollback means reverting the commit.

## Backlog follow-ups (applied at /ship)

- **T-07:** stays `enriched`, with `change: spotify-search-page` once proposed. Record the error codes (429 and 502, instead of 503) and the sidebar "Search" link the owner chose.
- **T-08:** reuse `SearchCursor` and the song shape, `SongResult`, for library tracks, adding `position`, `local` and `added_at` as extra keys.
- **T-11 and T-22:** add the YouTube Music source inside `SearchMusicHandler`. Mixing and deduplicating belong to T-22.
- **T-24:** album and artist items already carry `uri` and `id` for their pages.
