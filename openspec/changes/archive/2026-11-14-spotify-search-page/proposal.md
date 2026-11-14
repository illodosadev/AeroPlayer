# Proposal

## Why

T-07 (search and play from Spotify) was split in two. `spotify-search-api` added `GET /search/results`; this change adds the page that uses it, so the owner can type a query, see songs, albums and artists, page through them and play a song. With it, T-07 is done.

## What Changes

- **A `/search` page** (`GET /search`, named `search`), built only from Aero components, with a "Search" link in the dashboard sidebar.
- **Typing:**
  - the search starts 300 ms after the last keystroke, and Enter starts it at once;
  - a blank field sends nothing and shows "Type to search your music.";
  - the field takes at most 200 characters;
  - a newer search cancels the older one, and a late answer never replaces newer results.
- **Results:**
  - "Songs", "Albums" and "Artists", in Spotify's order;
  - an empty section is hidden, and 'No results for "…"' shows when all three are empty;
  - a song row shows its cover, title, artists, album, duration and a "Spotify" label.
- **"Load more"** per section:
  - it is disabled while loading;
  - it skips songs already shown and disappears at the end;
  - a failure keeps the loaded rows.
- **Playing:**
  - pressing a song plays only that song through the player's `play({uris: [uri]})`;
  - a song that is not playable is dimmed with "Not available".
- **States:**
  - "Connect Spotify" or "Reconnect Spotify" when the connection is missing or revoked;
  - one "not available right now" message for a 429, a 502 or a network failure;
  - the login page when the session ended.
- **AeroInput** gets an optional `maxlength` prop.

## Non-goals

- Mixing and deduplicating across services (T-22) and YouTube Music search (T-11).
- Album and artist pages: their rows are not links yet (T-24).
- "Add to queue" (T-13), "Add to playlist" (T-14), what plays after a song, and playback errors beyond one generic message (T-23).
- Keeping the query in the URL, search history and suggestions.
- Moving the kit's dashboard sidebar to Aero (T-25).

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `music-search`: adds the search page's requirements: typing, the sections and rows, "Load more", playing a song, and the connection and failure states. The endpoint's requirements do not change.

## Impact

- **New:**
  - `ShowSearchController` and the route `GET /search`;
  - `resources/js/pages/Search.vue`;
  - a `useMusicSearch` composable for typing, cancelling, paging and errors.
- **Changed:**
  - `AppSidebar.vue` gets the "Search" link;
  - `AeroInput.vue` gets `maxlength`, documented in the frutiger-aero skill;
  - `RoutesTest` gets the route.
- **Tests:**
  - a feature test for the page route;
  - browser tests with Spotify faked on the server and the fake SDK from `spotify-web-player`.
- **Backlog:** T-07 links to this change and closes at `/ship`.
