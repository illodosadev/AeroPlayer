# Tasks

Every test fakes Spotify with `Http::preventStrayRequests()` and `Http::fake()`. No test reaches Spotify. `spotify-library-overview` must be archived before this change is archived.

## 1. Shared pieces

- [x] 1.1 Add `App\Http\Paging\OpaqueCursor` and make `SearchCursor` delegate to it (design D3). Verify that `tests/Feature/Search/SearchCursorTest.php` and `SearchResultsTest.php` pass unchanged, and add `tests/Feature/Library/LibraryCursorTest.php` for the library scopes and the 100000 maximum.
- [x] 1.2 Extract `resources/js/components/SongRow.vue` from `Search.vue` and use it there (design D5). Verify that `tests/Browser/SearchPageTest.php` passes unchanged.

## 2. Library data

- [x] 2.1 Add `PlaylistSongResult`, `LikedSongResult`, `SongPage`, `SpotifyCatalogMapper::playlistSongs()` and `likedSongs()`, and the fixtures (design D2, D6). Verify in `tests/Unit/Library/SpotifySongMapperTest.php`:
  - positions with skipped entries and an offset;
  - local files, episodes and null items;
  - `added_at`;
  - the exact keys.
- [x] 2.2 Add `SpotifyLibraryReader` (client and mapper only, no repository), `PlaylistUnavailable` and the queries `GetPlaylist`, `GetPlaylistSongs` and `GetLikedSongs`, whose handlers inject the repository and run the connection and scope checks (design D1). Verify that `tests/Unit/ArchitectureTest.php` accepts them.

## 3. Routes

- [x] 3.1 Add the four routes and controllers, `LibrarySongsPageRequest`, the `MapsLibraryErrors` trait (also used by `ShowLibraryController`) with `App\Http\Controllers\Library\Concerns` added to `CONTROLLER_RULE_EXEMPT` in `tests/Unit/ArchitectureTest.php`, the JSON 401 paths in `bootstrap/app.php`, the `RoutesTest` rows, and placeholder pages (design D4). Verify in `tests/Feature/Library/PlaylistPageTest.php`:
  - "A visitor without a session";
  - "Seeing the songs of an own playlist", on the props and requests;
  - "A playlist with many songs", on the props and the JSON page;
  - "Opening a playlist directly";
  - "Opening a followed playlist";
  - "A local file in a playlist" and "A song that is not available", on the props;
  - "A song deleted from Spotify";
  - "An empty playlist", on the props;
  - "A playlist that is no longer available", both rows;
  - "A page cannot be loaded", the playlist rows.
- [x] 3.2 Verify in `tests/Feature/Library/LikedSongsPageTest.php`:
  - "Seeing the saved songs", on the props and requests;
  - "Thousands of saved songs", on the props and the JSON page;
  - "No saved songs";
  - "The saved-songs permission is missing on Liked Songs";
  - "A foreign cursor is rejected";
  - "A page cannot be loaded", the Liked Songs rows;
  - the JSON 401 for both endpoints.

## 4. Pages

- [x] 4.1 Add `usePagedSongs`, build `library/Playlist.vue` and `library/Liked.vue`, and link the rows of `Library.vue` (design D5). Verify in `tests/Browser/PlaylistPageTest.php` and `tests/Browser/LikedSongsPageTest.php`:
  - "Opening a playlist from the library";
  - "Opening Liked Songs from the library";
  - "The pages are usable with the keyboard and on a phone";
  - "Seeing the songs of an own playlist" and "Seeing the saved songs", on screen;
  - "A playlist with many songs" and "Thousands of saved songs", through "Load more";
  - "A double click loads one page";
  - "Loading more fails";
  - "Opening a followed playlist", "An empty playlist" and "A playlist that is no longer available", on screen;
  - "No saved songs" and "The saved-songs permission is missing on Liked Songs", on screen.
- [x] 4.2 Wire playing (design D5) and verify with the fake SDK:
  - "Playing a playlist from the start";
  - "Playing a playlist from a song";
  - "The position counts hidden entries";
  - "Playing a followed playlist";
  - "Playing a saved song";
  - "Songs that cannot play are not sent";
  - "A local file in a playlist" and "A song that is not available", pressing the disabled rows with a script;
  - "Playback cannot start".

## 5. Integration

- [x] 5.1 Run Pint on the changed PHP files and Prettier on the changed frontend files. Then verify that `openspec validate spotify-library-tracks --strict`, `npm run types`, `npm run build` and the full `vendor/bin/pest` run pass together, with no `composer dev` running.
- [x] 5.2 The owner runs the three manual checks of design D7 with the real account, and records the date and the result here.
  - **Result (2026-11-14, owner, real account):** all three pass. An own playlist plays and "Next" moves to the second song. A followed playlist plays and advances, and its page shows only the header, "Play" and "Spotify doesn't let this app list the songs of playlists you don't own.", as designed. Liked Songs plays from the second song and "Next" moves to the third.

## Workflow follow-up

- `/review-change spotify-library-tracks` before `/opsx-apply`.
- `/ship spotify-library-overview` before this change's `/ship`.
- At `/ship`:
  - merge the `music-library` delta;
  - close T-08;
  - apply the backlog follow-ups from design (T-08, T-12, T-13, T-14, T-22, T-24).
