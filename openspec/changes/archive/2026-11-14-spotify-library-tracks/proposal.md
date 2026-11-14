# Proposal

## Why

T-08 (Spotify library) is split in two changes by screen. `spotify-library-overview` built `/library` with every playlist and the Liked Songs count. This change opens them: a playlist's songs and the saved songs, page by page, and playing them. With it, T-08 is done.

## What Changes

- **A playlist page** (`GET /library/playlists/spotify/{id}`, named `library.playlist`) and a **Liked Songs page** (`GET /library/liked`, named `library.liked`), both Aero pages. The rows of `/library` link to them.
- **The playlist page** shows the cover, title, owner and Spotify's song count:
  - it lists songs 50 at a time, only for playlists the owner owns or collaborates on;
  - a followed playlist explains that Spotify doesn't let the app list its songs, and keeps "Play";
  - an empty playlist says so and disables "Play";
  - a playlist that is gone or forbidden says "This playlist is no longer available." and links back.
- **The Liked Songs page** lists saved songs 50 at a time, newest first, with Spotify's count. A missing permission asks to reconnect without asking Spotify.
- **Songs use the app's format** from `spotify-search-api`:
  - playlist songs add `position` (Spotify's index) and `local`;
  - saved songs add `added_at`;
  - local files and unplayable songs are dimmed, and deleted songs and podcast episodes are hidden.
- **"Load more"** asks a JSON endpoint for the next page with an opaque cursor, as the search does.
- **Playing:**
  - "Play" plays the playlist as a Spotify context from the start;
  - a song plays it from that song's position;
  - a followed playlist plays as a context from its start;
  - a saved song plays the playable songs of its page of 50.
- **Shared pieces:**
  - the song row moves out of `Search.vue` into a component;
  - the search cursor's encryption becomes a shared opaque cursor.
- **States** follow `/library`, all with status 200.

## Non-goals

- A cache for song pages, by the owner's choice: only the `/library` overview is kept.
- The app's own queue and what plays after a page of saved songs (T-13), "Add to playlist" (T-14), YouTube Music (T-12) and mixing services (T-22).
- Changing the Spotify library.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `music-library`: adds the playlist and Liked Songs pages, their song format, "Load more" and playing. `spotify-library-overview` must be archived first, since it creates the capability.

## Impact

- **New:**
  - the queries `GetPlaylist`, `GetPlaylistSongs` and `GetLikedSongs` in `app/Application/Library`, with their results;
  - song mapping for playlist items and saved tracks in `SpotifyCatalogMapper`;
  - page and JSON controllers in `app/Http/Controllers/Library`;
  - `resources/js/pages/library/Playlist.vue` and `Liked.vue`;
  - a song-row component and a paging composable.
- **Changed:** `Library.vue` (links), `Search.vue` (shared row), `SearchCursor` (shared cursor) and `RoutesTest`.
- **Tests:** feature tests for the props, requests, cursors and states; browser tests for paging and playing with the fake SDK. The search tests must keep passing.
- **Manual:** T-08's three `@manual` playback checks with the real account.
- **Backlog:** T-08 closes at `/ship`.
