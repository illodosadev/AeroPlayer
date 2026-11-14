# Design

## Context

See proposal.md, "Why". The requirements are in `specs/spotify-playback/spec.md`. The research behind them is in T-06's "Restricciones descubiertas":

- `getOAuthToken` runs on every `connect()` and whenever the token expires;
- `setVolume` takes 0 to 1 and has no effect on iOS;
- `activateElement()` must run from a click;
- `player_state_changed` is not a continuous progress feed;
- EME needs a secure context.

What exists:

- **Server:** `GET /spotify/token` and `PUT /spotify/player/play` (`spotify-playback-api`). Both are JSON with `no-store`, and both answer a JSON 401, plus a JSON 419 `session_expired` on PUT.
- **Shared props:** `name` (`APP_NAME`) and `spotify.{status, connected}`.
- **Pages:**
  - `Dashboard` renders the kit's `AppLayout`;
  - `Styleguide` and `settings/Spotify` render `AeroBackground` themselves;
  - `auth/Login` is the only guest page;
  - no page uses an Inertia persistent layout yet.
- **`AeroPlayerBar`:** only emits events. Its parent owns `title`, `artist`, `coverSrc`, `playing`, `position` and `duration` (in seconds), `volume` (0–100) and the `*Disabled` flags. It has the `notice` and `notice-actions` slots. The volume slider has no value text yet.
- **Browser tests** (Pest 4 with Playwright) run the app in the test process, so `config()->set()`, routes registered in a test and `Http::fake()` apply to the page under test. `signedInOwnerVisit()` signs in through the login form. The suite must avoid a Tab walk past the last control.
- **Secure context:** `http://127.0.0.1` counts as one, so the manual check works on the local address that Spotify's redirect already requires.

## Goals / Non-Goals

**Goals:**

- One SDK player per tab that survives Inertia navigation, with one source of truth for the bar.
- A fake SDK good enough to test every automatable scenario without Spotify.

**Non-Goals:**

- Handling `not_ready`, `account_error`, `playback_error` or a `null` state when playback moves away (T-23). This change only logs nothing and keeps the last state.
- Restyling the dashboard or the Aero navigation (T-25).

## Decisions

### D1. A persistent layout for every signed-in page

- `resources/js/app.ts` gives every page whose name does not start with `auth/` the layout `PlayerLayout`, unless the page sets its own layout. Future pages (T-07, T-08) get the player without opting in.
- `PlayerLayout` renders the page in its slot, plus a fixed bar at the bottom of the viewport: `<div id="app-player">` holding an `AeroPlayerBar`.
- The layout adds bottom padding equal to the bar's height, so no page content hides behind the bar.
- Inertia keeps a persistent layout mounted across visits, so the player lives as long as the tab stays on signed-in pages.
- **The kit's sidebar on the dashboard** is `fixed inset-y-0 h-svh`, so the bar would cover its user menu at the bottom left. The layout therefore:
  - measures the bar with a `ResizeObserver` and stores its height in `--app-player-height`;
  - shortens the kit's fixed sidebar container to end above the bar, with a scoped CSS rule. This kit version gives that container no `data-slot`, so the rule targets `div.fixed:has(> [data-sidebar='sidebar'])`.

  The user menu then stays visible and clickable. The rule goes away when T-25 removes the kit's shell.

*Alternatives:*

- Opting in page by page with `defineOptions({ layout })`. Rejected: the owner asked for every signed-in page, and every new page would have to remember it.
- Mounting the player outside Inertia in `app.ts`. Rejected: the layout needs the shared props and must stop the player on the login page.

### D2. A singleton composable, `useSpotifyPlayer`

- Module-level reactive state, so there is one per tab, holds:
  - the device id and whether it is ready;
  - the current track (`uri`, `title`, `artists[]`, `album`, `image`, `durationMs`);
  - `paused`, `positionMs`, `timestamp`, `hasNext`, `autoplayBlocked`, `volume` (0–100) and `lastSeekAt` (for T-19).
- Methods: `start(sdkUrl, name)`, `stop()`, `togglePlay()`, `seek(ms)`, `setVolume(percent)`, `next()`, `previous()` and `play(request)`.
- `previous()` follows classic players: with more than 3 seconds elapsed it calls `seek(0)`, otherwise `previousTrack()`. The manual check showed that the SDK's `previousTrack()` always moves to the previous track whatever the position, and the owner chose the classic behavior.
- T-13, T-16, T-17 and T-19 read the same state.

### D3. Loading the SDK

`start()`:

1. sets `window.onSpotifyWebPlaybackSDKReady`;
2. appends `<script src="{sdkUrl}">` once;
3. creates the player when the SDK calls back, or at once if `window.Spotify` already exists.

The player is created with:

- `name` set to the `name` prop;
- `volume` set to the remembered volume (D8) divided by 100;
- `getOAuthToken` from D4.

The composable then registers `ready`, `not_ready`, `player_state_changed` and `autoplay_failed`, and calls `connect()`. A second `start()` while a player exists does nothing.

With an empty `sdkUrl`, `start()` loads nothing: the device stays not ready and the controls stay disabled. The tests rely on this (D11).

### D4. Talking to the server

- Every request sends `Accept: application/json` and the session cookie.
- **`getOAuthToken(cb)`** fetches `GET /spotify/token`:
  - 200: calls `cb(access_token)`;
  - 401 or 419: `window.location.assign('/login')`;
  - anything else: does not call `cb`, and T-23 shows the error.
- **`play()`** sends `PUT /spotify/player/play` with `Content-Type: application/json` and `X-XSRF-TOKEN`, read from the `XSRF-TOKEN` cookie. On a non-2xx answer it rejects with `{status, error}`; a 401 or 419 also goes to `/login`.

### D5. `play({contextUri?, uris?, offset?})`

1. Reject with `Error('play() needs exactly one of contextUri or uris')` when neither or both are given, or when the device is not ready. Nothing is sent.
2. Call `player.activateElement()`. `play()` runs from a click in T-07 and T-08.
3. Send `{device_id, context_uri | uris, offset ?? {position: 0}}`.

The composable also sets `window.aeroPlayer = { play }`. Feature code imports the composable; the global exists for the browser tests and for debugging. It needs a signed-in page anyway.

### D6. From SDK state to the bar

On `player_state_changed(state)`:

- the composable ignores a state whose `timestamp` is older than the current one;
- it maps `track_window.current_track` to the track: the artists' names joined by ", ", `album.images[0].url`, `duration_ms`;
- it reads `position`, `paused` and `timestamp`, and sets `hasNext` from `track_window.next_tracks.length > 0`;
- a `null` state (playback moved away) keeps the last state for T-23 to handle.

`PlayerLayout` passes to the bar:

- the track, with `playing = !paused`;
- the position and duration in seconds;
- the volume;
- `playDisabled` and `previousDisabled` set to `!ready`, and `nextDisabled` set to `!ready || !hasNext`;
- `seekDisabled` set to `!ready || !track`.

### D7. Interpolated progress

- While a track plays, a 250 ms interval updates a `now` ref. The displayed position is `positionMs + (now − timestamp)`, capped at the duration. While paused it is `positionMs`.
- A seek sets `positionMs` to the target and `timestamp` and `lastSeekAt` to now, so the bar shows the new time at once.
- The interval stops when paused or stopped.

### D8. The remembered volume

- `localStorage['aeroplayer.volume']` holds an integer from 0 to 100. Every read and write is wrapped in try/catch, because storage can be blocked.
- With nothing stored, or an invalid value, the volume is 50.
- `setVolume(percent)` calls `player.setVolume(percent / 100)`, stores the value and updates the state.
- `AeroPlayerBar` gives its volume slider the value text `${volume}%`, the only change to the component.

### D9. Blocked autoplay

- `autoplay_failed` sets `autoplayBlocked`, and the bar's `notice` slot shows "Press play to start listening".
- `togglePlay()` calls `activateElement()` and then `resume()` when `autoplayBlocked` is set or the track is paused. Otherwise it calls `pause()`.
- The next state with `paused = false` clears the flag.

### D10. Stopping

- `PlayerLayout` watches `page.props.spotify.connected`:
  - when it turns `true`, it calls `start()`;
  - when it turns `false` (for example after "Disconnect Spotify"), it calls `stop()`.
- `stop()` calls `player.disconnect()`, removes the listeners and resets the state, while keeping the volume.
- The layout's `onBeforeUnmount` also calls `stop()`. Logging out lands on `/login`, which has no layout, so the player disconnects before the login page shows.
- Without a connection, the bar shows the notice "Connect Spotify to play your music." and a notice action "Spotify settings", a link to `/settings/spotify`. The controls stay disabled.

### D11. The SDK URL

- `config/services.php` gets `spotify.sdk_url = env('SPOTIFY_SDK_URL', 'https://sdk.scdn.co/spotify-player.js')`.
- `HandleInertiaRequests` adds `sdkUrl` to the shared `spotify` props for signed-in users. It is configuration, not data, so it needs no query.
- **`phpunit.xml` sets `SPOTIFY_SDK_URL` to an empty value.** Browser tests that create a connection, such as `SpotifySettingsTest`, therefore never load the real SDK from the internet or let it reach Spotify. That keeps `app-foundation`'s rule that the browser suite needs no external network service. Only `SpotifyPlayerTest` points the URL at the fake SDK (D12).

### D12. The fake SDK

`tests/Browser/fixtures/fake-spotify-sdk.js` defines `window.Spotify.Player` and `window.__fakeSpotify`:

- `players`: the options each player was created with;
- `calls`: the method names and arguments, in order;
- `emit(event, payload)`: runs the registered listeners;
- `requestToken()`: runs `getOAuthToken` and resolves with the value passed to the callback.

It then calls `window.onSpotifyWebPlaybackSDKReady()`. The fake never emits `ready` on its own, so each test decides when the device is ready.

`tests/Browser/SpotifyPlayerTest.php`:

- in `beforeEach`, registers `GET /testing/fake-spotify-sdk.js`, which serves the fixture as `application/javascript`, and points `services.spotify.sdk_url` at it;
- uses `Http::fake()` for `api.spotify.com`;
- creates the owner's connection with a valid token, so `/spotify/token` sends nothing to Spotify;
- removes `aeroplayer.volume` from `localStorage` after the first visit, so a volume another test stored cannot leak into "volume 0.5".

The route lives only in that test's application, so `RoutesTest` and the route files are unaffected. The tests call `page.script()` to emit events and read `__fakeSpotify.calls`.

### D13. Existing tests

- `tests/Browser/SpotifySettingsTest.php` counts one glass panel. It now counts only the panels inside `#spotify-settings`, because the app bar is a second glass panel. The spec still holds: there is a glass panel.
- `AeroPlayerBarTest`'s 375 px loop over every `[data-aero=player-bar]` now also covers the app bar, so its notice link must keep a 44 px target.
- The styleguide focus walk now ends on that link.
- `OwnerAuthBrowserTest` opens the user menu at the bottom of the kit's sidebar on the dashboard. It must pass unchanged, which proves the sidebar rule in D1.

## Risks / Trade-offs

- [A test loads the real SDK and reaches Spotify] → `phpunit.xml` empties the SDK URL, and `start()` loads nothing without one (D3, D11).
- [The bar covers the kit's user menu] → The layout shortens the sidebar to end above the bar (D1), and `OwnerAuthBrowserTest` clicks that menu.
- [The fake SDK drifts from the real one] → It implements only the methods and events this change uses, named as in Spotify's reference. The manual check with real Premium covers the real SDK.
- [A fixed bar covers content on small screens] → The layout pads the page by the bar's height, and the 375 px checks run with the bar present.
- [The persistent layout on the dashboard nests the kit's `AppLayout` inside it] → Accepted until T-25 replaces the dashboard shell.
- [Autoplay rules differ between browsers] → `activateElement()` runs on every user-started play, and `autoplay_failed` is handled.
- [The global `window.aeroPlayer`] → It exposes only `play`, which needs the signed-in session and CSRF token anyway.

## Migration Plan

No schema change. Run `npm run build` and deploy as usual. Rollback means reverting the commit.

## Manual checks

2026-11-14, step 4.1, with the owner's real Spotify Premium on `http://127.0.0.1:8000` in desktop Chrome:

- The device named after `APP_NAME` appeared in the official Spotify app's device list.
- `window.aeroPlayer.play({contextUri: …})` with a real album played its first track in the tab, and the bar matched it.
- Pause, Play, volume at 30 %, a seek to 1:30 and Next worked as expected.
- **Previous** always moved to the previous track, before and after the first 3 seconds: the SDK's `previousTrack()` ignores the position. The owner chose the classic behavior instead, so `previous()` now restarts the track after 3 seconds (D2, scenario "Previous restarts a track after 3 seconds"). The new behavior is covered by the browser tests; the owner may confirm it by ear.

## Backlog follow-ups (applied at /ship)

- **T-06:** `status: done`, because both changes are archived.
- **T-07 and T-08:** start playback with `useSpotifyPlayer().play({contextUri | uris, offset})` from a click handler.
- **T-13, T-16, T-17 and T-19:** read the composable's state, including `lastSeekAt`. T-25 turns `PlayerLayout` into `AeroAppLayout`.
- **T-23:** handles `not_ready`, `account_error`, `playback_error`, a `null` state and the non-auth error codes of the token endpoint, inside `useSpotifyPlayer`.
