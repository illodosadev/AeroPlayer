# Tasks

Every test fakes Spotify with `Http::preventStrayRequests()` and `Http::fake()`. No test reaches Spotify.

## 0. Spike

- [x] 0.1 With the real account, run the two checks of design D0 from the browser console. Record the date and the result here, without ids. If a followed playlist does not play as a context, stop and revise T-08 before step 1.1.
  - **Result (2026-11-14, owner, real account):** both checks pass. A playlist the owner only follows plays as a context and advances with "Next", and three saved songs passed as `uris` start at the second and advance to the third.

## 1. The library data

- [x] 1.1 Add `PlaylistResult`, `SpotifyCatalogMapper::playlists()`, `SpotifyScopes::grants()` and the fixture `tests/Fixtures/spotify/me-playlists.json` (design D2, D5). Verify in `tests/Unit/Library/SpotifyPlaylistMapperTest.php`:
  - the mapping table;
  - `can_list_tracks` for an own, a followed and a collaborative playlist;
  - `total` null without `items`;
  - null entries skipped;
  - the exact keys.
- [x] 1.2 Add the query `GetLibrary` with `LibraryOverview`, and the command `RefreshLibrary` (design D1, D3). Verify that `tests/Unit/ArchitectureTest.php` accepts them.

## 2. Routes and props

- [x] 2.1 Add `Library/ShowLibraryController`, `Library/RefreshLibraryController`, the routes `GET library` (named `library`) and `POST library/refresh` (named `library.refresh`), their rows in `tests/Feature/RoutesTest.php`, and a placeholder `resources/js/pages/Library.vue` (design D3, D4). Verify in `tests/Feature/Library/LibraryPageTest.php`:
  - "A visitor without a session";
  - "Seeing the library", on the props;
  - "Many playlists", and that reading stops after 20 requests (1000 playlists) even when Spotify still gives a `next`;
  - "Playlist props have the common format";
  - "A playlist without a cover", on the props;
  - "A playlist the owner only follows", on the props;
  - "A collaborative playlist", on the props.
- [x] 2.2 Verify the cache and the states in the same file:
  - "The library is served from the cache";
  - "The cache expires after 10 minutes";
  - "Refreshing the library";
  - "A new connection does not read the old cache";
  - "Spotify not connected";
  - "Disconnecting hides the cached library";
  - "Spotify access was revoked";
  - "The saved-songs permission is missing";
  - "The playlists permission is missing";
  - "Spotify fails";
  - "Spotify is busy and nothing is kept";
  - "An empty library", "Saved songs but no playlists" and "A missing permission is not an empty library", on the props.

## 3. The page

- [x] 3.1 Build `Library.vue` and add the "Library" link to `AppSidebar.vue` (design D4). Verify in `tests/Browser/LibraryPageTest.php`:
  - "Opening the library";
  - "The page is usable with the keyboard and on a phone";
  - "Seeing the library";
  - "Large saved-song counts are grouped";
  - "A playlist without a cover";
  - "A playlist the owner only follows";
  - "A collaborative playlist".
- [x] 3.2 Verify the page states in the same file:
  - "An empty library";
  - "Saved songs but no playlists";
  - "A missing permission is not an empty library";
  - "Spotify not connected";
  - "Spotify access was revoked";
  - "The saved-songs permission is missing";
  - "The playlists permission is missing";
  - "Spotify fails";
  - "Trying again";
  - "Spotify is busy and nothing is kept";
  - "Refreshing the library", through the button.

## 4. Integration

- [ ] 4.1 Run Pint on the changed PHP files and Prettier on the changed frontend files. Then verify that `openspec validate spotify-library-overview --strict`, `npm run types`, `npm run build` and the full `vendor/bin/pest` run pass together, with no `composer dev` running.

## Workflow follow-up

- `/review-change spotify-library-overview` before `/opsx-apply`.
- At `/ship`:
  - merge the new `music-library` capability;
  - keep T-08 open;
  - apply the backlog follow-ups from design (T-08, T-12, T-14, T-22, T-25).
- Then `/opsx-propose spotify-library-tracks`.
