# Tasks

Every browser test fakes Spotify on the server with `Http::preventStrayRequests()` and `Http::fake()`. No test reaches Spotify.

## 1. Route and entry point

- [x] 1.1 Add `Search/ShowSearchController`, the route `GET search` named `search`, its row in `tests/Feature/RoutesTest.php`, and a placeholder `resources/js/pages/Search.vue` (design D1). Verify in `tests/Feature/Search/SearchPageTest.php`:
  - the owner gets the `Search` component with status 200, with and without a Spotify connection;
  - "A visitor without a session": a guest is redirected to `/login`;
  - no request reaches Spotify.
- [x] 1.2 Add the "Search" link to `AppSidebar.vue` and the `maxlength` prop to `AeroInput.vue`, documented in `.claude/skills/frutiger-aero/references/components.md` (design D1, D5).

## 2. The search logic

- [x] 2.1 Add `resources/js/composables/useMusicSearch.ts` (design D2):
  - the 300 ms debounce, Enter and the blank field;
  - cancelling with `AbortController` and dropping stale answers by generation;
  - `loadMore` with the loading guard and the dedupe by `id`;
  - the answer table, including the 401 redirect, the 409 states and the 422 re-search.

  Verify with `npm run types`.

## 3. The page

- [x] 3.1 Build `Search.vue` (design D1, D3, D5):
  - the Aero panel with the "Back" link and the search form;
  - the connect and reconnect states;
  - the "Type to search your music.", 'No results for "…"' and "not available" messages;
  - the three sections with their song, album and artist rows and "Load more".

  Verify in `tests/Browser/SearchPageTest.php`:
  - "Opening the search page";
  - "The page is usable with the keyboard and on a phone";
  - "Searching Spotify from the page";
  - "No results";
  - "An empty section is hidden";
  - "A song row shows its details and its service";
  - "An item without an image";
  - "Searching without Spotify connected";
  - "Spotify access was revoked".
- [x] 3.2 Verify the typing scenarios in the same file:
  - "Typing quickly sends one search";
  - "Enter searches at once";
  - "Surrounding spaces and an empty field";
  - "The field takes at most 200 characters";
  - "A late answer does not replace newer results", with the in-page fetch delay.
- [x] 3.3 Verify the paging scenarios:
  - "Loading more songs";
  - "A double click sends one request";
  - "A song repeated across pages shows once";
  - "Loading more fails";
  - "No more results to load".
- [x] 3.4 Wire playing (design D4) and verify, with the fake SDK:
  - "Playing a song from the results";
  - "A song that is not available", checking `disabled` and pressing the row with a script, because Playwright's `click()` waits on a disabled button (design D6);
  - "Playback cannot start".
- [x] 3.5 Verify the failure scenarios:
  - "Spotify cannot answer", all rows;
  - "The rate-limit pause is respected";
  - "An expired token does not stop the search";
  - "The session ended".

## 4. Integration

- [x] 4.1 Run Pint on the changed PHP files. Then verify that `openspec validate spotify-search-page --strict`, `npm run types`, `npm run build` and the full `vendor/bin/pest` run pass together, with no `composer dev` running.

## Workflow follow-up

- `/review-change spotify-search-page` before `/opsx-apply`.
- At `/ship`:
  - merge the `music-search` delta;
  - close T-07;
  - apply the backlog follow-ups from design (T-07, T-08, T-13, T-14, T-23, T-24, T-25).
