# Design

## Context

See proposal.md, "Why". The requirements are in `specs/music-search/spec.md`; the endpoint they build on is in `openspec/specs/music-search/spec.md`.

What exists:

- **`GET /search/results`** (`spotify-search-api`):
  - without a section it answers `{songs, albums, artists}`, each `{items, next_cursor}`;
  - with `section` and `cursor` it answers one section's `{items, next_cursor}`;
  - errors: 401 `unauthenticated`, 409 `spotify_not_connected` or `spotify_reconnect_required`, 422 for validation, 429 `rate_limited` and 502 `spotify_unavailable`.
- **`useSpotifyPlayer()`** (`resources/js/composables/useSpotifyPlayer.ts`):
  - `play({uris})` calls `activateElement()` and `PUT /spotify/player/play`;
  - it throws when the device is not ready, and `PlayerRequestError(status, code)` when the endpoint fails;
  - after a 401 or 419 it already sends the owner to `/login`.
- **Every signed-in page** gets `PlayerLayout` from `app.ts`, and the shared prop `spotify.status` (`not_connected`, `connected` or `reconnect_required`).
- **The pages in Aero:** `settings/Spotify.vue` is the model: `AeroBackground`, a `main` with a "Back" link, `AeroGlassPanel`, and a connect button that is a GET form to `/spotify/connect`. The dashboard still uses the kit's shadcn sidebar until T-25.
- **The Aero components:** `AeroInput` has no `maxlength`. `AeroCover` shows the generic cover for a null or failed `src`. `formatTime(seconds)` gives "m:ss".

## Goals / Non-Goals

**Goals:**

- One composable owns the search logic (debounce, cancelling, stale answers, paging, errors), so the page only renders state, and T-11/T-22 can feed it more sources without touching the page.
- The page follows the Aero rules: Aero components only, a 2 px focus outline, 44 px touch areas and the 375 px layout.

**Non-Goals:**

- Album and artist links (T-24), queue and playlist actions (T-13, T-14), playback errors beyond one message (T-23), migrating the sidebar (T-25).

## Decisions

### D1. The page and its route

- **`ShowSearchController`** (`app/Http/Controllers/Search`) renders the Inertia page `Search` with no props of its own. It is in the `auth` group as `GET search`, named `search`.
- **The connection state comes from the shared prop `spotify.status`**, so the controller asks nothing. A missing connection still answers 200.

  *Alternative:* an `error` prop computed by the controller, as T-07 suggested. Rejected: it would repeat the query `HandleInertiaRequests` already runs for every page.
- **The page is an Aero page like `settings/Spotify.vue`**: `AeroBackground`, a `main#search-page` with a "Back" link to the dashboard, and an `AeroGlassPanel` holding the field and the results.

  *Alternative:* `AppLayout` with the kit's sidebar. Rejected: it renders shadcn elements with `data-slot`, and T-25 asks new pages to have none.
- **`AppSidebar.vue`** gets `{title: 'Search', href: '/search', icon: Search}` after "Dashboard".

### D2. `useMusicSearch`, the search state

`resources/js/composables/useMusicSearch.ts` returns reactive state and three actions. The state:

- `query`: the field's text;
- `status`: `idle`, `loading`, `results`, `empty`, `unavailable`, `not_connected` or `reconnect_required`;
- `searched`: the trimmed query of the shown results;
- `sections`: `songs`, `albums` and `artists`, each `{items, nextCursor, loading, failed}`.

The actions:

- **`input(text)`** restarts a 300 ms timer. A blank text cancels the timer and the request in progress and goes back to `idle`.
- **`submit()`**, from the form's submit event (Enter), cancels the timer and searches at once. Cancelling the timer is what keeps the 300 ms wait from sending the same search again. There is no same-query guard: pressing Enter on the query that just failed is the retry.
- **`loadMore(section)`** returns at once while that section is loading, so a double click sends one request. It asks for `section` and `cursor`, adds only the items whose `id` is not in the section yet, and stores the new cursor. On failure it sets `failed` and keeps the items.

How a search runs:

- Each search aborts the previous `AbortController` and increments a `generation` number.
- An answer whose generation is not the current one is dropped. This covers answers that arrive after the abort, and "Load more" answers that arrive after a new search started.
- Requests are `fetch('/search/results?' + URLSearchParams)` with `Accept: application/json` and `credentials: 'same-origin'`.

How an answer is handled:

| Answer | First search | "Load more" |
|---|---|---|
| 200 | `results`, or `empty` when the three sections are empty | append, new cursor, `failed` false |
| 401 | `window.location.assign('/login')` | same |
| 409 `spotify_not_connected` / `spotify_reconnect_required` | that status | that status |
| 422 | `unavailable` | a stale cursor (an `APP_KEY` change): run the search again |
| 429, 502, other, network error | `unavailable`, results cleared | `failed` true, items kept |
| `AbortError` | ignored | ignored |

The initial `status` is `not_connected` or `reconnect_required` when the shared `spotify.status` says so; otherwise `idle`.

*Alternative:* Inertia partial reloads with the query in the URL. Rejected: the endpoint is JSON with an opaque cursor, the page needs per-section paging, and keeping the query in the URL is a non-goal.

### D3. The rows

- **Markup lives in `Search.vue`**, with scoped CSS that reads only `--aero-*` tokens. Each section is a `section` with an `h2` ("Songs", "Albums", "Artists") and a `ul`.
- **A song row** is a `button` spanning the row:
  - an `AeroCover` (`:size="48"`, `alt` "<title> cover") and the title;
  - the artists joined with ", ", the album and `formatTime(duration_ms / 1000)`;
  - a "Spotify" label from a `source → label` map, ready for YouTube Music.

  A song with `playable` false renders the button `disabled`, dimmed, with "Not available" in place of the duration. A disabled button cannot be pressed and is left out of the Tab order.
- **Album and artist rows** are plain list items (cover, title, artists and year; picture and name). They become links in T-24.
- **"Load more"** is an `AeroButton` under each section with a next cursor, `disabled` while that section loads. A failed load shows the "not available" message above it (`role="status"`).

*Alternative:* a new base component, `AeroTrackRow`, on `/styleguide`. Rejected for now: the only list is this one. T-08 extracts it when the library needs the same row.

### D4. Playing a song

- The song button calls `useSpotifyPlayer().play({uris: [song.uri]})`.
- Any rejection, whether the player is not ready or a `PlayerRequestError`, shows "Playback couldn't start. Try again in a moment." in the page's status line and keeps the results. Specific playback messages are T-23's.

### D5. Connection states and the field

- **`not_connected`:** the panel shows "Connect Spotify to search its catalog." and a GET form to `/spotify/connect` with the `AeroButton` "Connect Spotify". The field is not shown, so no search can be sent.
- **`reconnect_required`:** the same with "Spotify access was revoked." and "Reconnect Spotify".
- **The field:**
  - an `AeroInput` with `id="search-query"`, the label "Search songs, albums and artists", `type="search"`, `autocomplete="off"` and `:maxlength="200"`;
  - it sits inside `<form role="search" @submit.prevent>`, so Enter submits;
  - `AeroInput` gets an optional `maxlength` prop, bound on its `<input>`, and `references/components.md` documents it.

### D6. Tests

- **Feature, `tests/Feature/Search/SearchPageTest.php`:** the owner gets the `Search` component with status 200, also without a connection; a guest is redirected to `/login`; no request reaches Spotify. `RoutesTest` gets the row `GET|HEAD search search [web, auth]`.
- **Browser, `tests/Browser/SearchPageTest.php`:**
  - Spotify is faked on the server with `Http::fake` callbacks keyed on the query's `q`, `type` and `offset`.
  - Counting search requests uses `Http::recorded()`, which works because the browser server runs in the test process.
  - The playing scenarios load the fake SDK through a test-only route, as in `SpotifyPlayerTest`. They assert the `PUT https://api.spotify.com/v1/me/player/play` that Spotify receives.
  - **Out of order:** a script wraps `window.fetch` in the page and holds back the answer for `q=Daft`. Delaying it in the browser fixes the order of the answers without depending on how the test server handles concurrent requests.
  - **A song that is not available:** its row is a `disabled` button, and Playwright's `click()` waits until an element is enabled, so it would time out. The test checks `disabled` and then presses the row with a script (`el.click()`), which a disabled button ignores.
  - **Session ended:** a script posts `/logout` with the XSRF cookie before typing.
  - **Rate limit:** `$this->travel()` between searches.
  - **Aero rules:** the focus walk from `#search-page`, the 375 px check, and no `data-slot`.
  - Helper names carry a `search` prefix, because Pest declares them globally.

## Risks / Trade-offs

- [Timing-based browser tests can flake] → They wait on visible state (rows, messages), not on fixed sleeps, except the debounce check: it waits more than 300 ms and then counts requests.
- [The page and the dashboard look different until T-25] → It is accepted; the sidebar link is the only entry point that needs the kit.
- [A disabled song button gives no tooltip about why] → "Not available" is shown in the row itself.

## Migration Plan

No schema change. Deploy as usual. Rollback means reverting the commit.

## Backlog follow-ups (applied at /ship)

- **T-07:** `status: done`. Record that the page reads the shared `spotify.status` instead of an error prop.
- **T-08:** extract the song row from `Search.vue` into a shared component when the library needs it; reuse `useMusicSearch`'s paging and dedupe approach.
- **T-13 and T-14:** their actions go in the song row of `Search.vue`, disabled when `playable` is false.
- **T-23:** replace the generic "Playback couldn't start" message with specific playback errors.
- **T-24:** turn the album and artist rows into links.
- **T-25:** the dashboard sidebar is the last kit piece on the way to `/search`.
