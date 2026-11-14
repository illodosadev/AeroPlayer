# Proposal

## Why

T-08 (Spotify library) lets the owner reach their usual music without the official app. It is split in two changes by screen. This first one builds `/library`: every Spotify playlist and the Liked Songs count in one unified view, with no tab per service, so YouTube Music (T-12) can join the same list later. The second change, `spotify-library-tracks`, opens a playlist or Liked Songs and plays them.

## What Changes

- **A `/library` page** (`GET /library`, named `library`), built from Aero components, with a "Library" link in the dashboard sidebar:
  - one "Playlists" list in Spotify's order, each with its cover, title, a "Spotify" label, "{n} songs" or "by {owner}", and "Collaborative" when it applies;
  - a "Liked Songs" entry with its count.
- **Reading Spotify:**
  - every playlist, 50 per request, up to 1000;
  - the Liked Songs count from one request with `limit=1`;
  - nothing is sent for a part whose scope is missing.
- **Playlists use the app's format:** `source`, `id`, `uri`, `title`, `image`, `owner`, `collaborative`, `can_list_tracks` and `total`.
- **A 10-minute cache** keyed by the connection, so a reconnect never reads the old one. "Refresh" (`POST /library/refresh`) drops it.
- **States, all with status 200 and no error page:**
  - no connection;
  - revoked access;
  - a missing scope;
  - an empty library, or no playlists but some liked songs;
  - Spotify busy (429) with no cache;
  - any other failure, with "Try again".

## Non-goals

- Opening a playlist or Liked Songs, "Load more" and playing (`spotify-library-tracks`). The rows are not links yet.
- Making `/library` the home page after login (T-25), "My playlists" (T-14), YouTube Music (T-12) and mixing services (T-22).
- Changing the Spotify library, saved albums, followed artists and podcasts.

## Capabilities

### New Capabilities

- `music-library`: the owner's library from their music services in one unified view, with one service-independent format. Spotify is the only source for now. This change adds the overview; `spotify-library-tracks` adds the playlist and Liked Songs pages.

### Modified Capabilities

None.

## Impact

- **New:**
  - the query `GetLibrary` and the command `RefreshLibrary` in `app/Application/Library`, with their results;
  - a playlist mapping in `SpotifyCatalogMapper`;
  - `Library/ShowLibraryController` and `Library/RefreshLibraryController`;
  - `resources/js/pages/Library.vue`;
  - fixtures in `tests/Fixtures/spotify`.
- **Changed:**
  - `AppSidebar.vue` gets the "Library" link;
  - `RoutesTest` gets the two routes.
- **Tests:**
  - feature tests for the props, the requests sent, the cache and every state;
  - browser tests for the page, its texts and the Aero rules.
- **Before implementing:** a spike with the real account checks that a playlist the owner only follows plays as a context and that Liked Songs play by their uris. If the first fails, implementation stops and T-08 is revised.
- **Backlog:** T-08 links to this change and stays open for `spotify-library-tracks`.
