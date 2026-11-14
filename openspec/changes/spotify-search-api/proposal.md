# Proposal

## Why

T-07 (search and play from Spotify) is split in two. This first change adds the server side: one JSON endpoint that searches Spotify's catalog for songs, albums and artists and answers in the app's own format, page by page. The second change, `spotify-search-page`, builds the `/search` page on top of it. T-07 asks for a search shaped as one list independent of the service, so YouTube Music (T-11) can join it later without a new contract.

## What Changes

- `GET /search/results?q=…` searches Spotify once and answers the three sections: songs, albums and artists, up to 10 each, in Spotify's order. It sends `type=track,album,artist`, `limit=10`, `offset=0` and `market=from_token`.
- `GET /search/results?q=…&section=songs|albums|artists&cursor=…` answers one section's next page as `{items, next_cursor}`.
- **Items use the app's format**, never Spotify's field names:
  - a song has `source`, `id`, `uri`, `title`, `artists`, `album`, `duration_ms`, `image` and `playable`;
  - an album has `source`, `id`, `uri`, `title`, `artists`, `year` and `image`;
  - an artist has `source`, `id`, `uri`, `title` and `image`.
- **The cursor is opaque.** It is encrypted by the app, carries the section, the query and the offset, and is `null` at the end or when the next offset would reach Spotify's 1000 limit.
- **The query** is trimmed, must be 1 to 200 characters and is sent to Spotify as typed. A missing or invalid query, section or cursor answers 422 without calling Spotify.
- **Failures** answer JSON with the codes the player endpoints already use:
  - 401 `unauthenticated` for a guest's JSON request;
  - 409 `spotify_not_connected` and 409 `spotify_reconnect_required`;
  - 429 `rate_limited` with `retry_after`;
  - 502 `spotify_unavailable`, which also covers any other refusal from Spotify, such as a 403.

  This follows the owner's choice to reuse the player's error codes instead of T-07's 503.

## Non-goals

- The `/search` page, its debounce, "Load more" and playing a result (`spotify-search-page`).
- Mixing and deduplicating results across services (T-22) and YouTube Music search (T-11).
- Album and artist pages (T-24); searching playlists, podcasts or audiobooks; a result cache.

## Capabilities

### New Capabilities

- `music-search`: searching the owner's music services for songs, albums and artists, with one service-independent result format and opaque paging. Spotify is the only source for now.

### Modified Capabilities

None.

## Impact

- **New:**
  - the query `SearchMusic` with its results in `app/Application/Search`;
  - the cursor codec;
  - a Spotify catalog mapper in `app/Spotify`;
  - `Search/ShowSearchResultsController` and its form request.
- **Changed:**
  - `bootstrap/app.php` answers a JSON 401 for `search/results` too;
  - `routes/web.php` and `tests/Feature/RoutesTest.php` get the route.
- **Tests:** feature tests with Spotify fixtures in `tests/Fixtures/spotify` and `Http::fake()`. No test reaches Spotify.
- **Backlog:** T-07 links to this change and stays open for `spotify-search-page`.
