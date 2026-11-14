# Proposal

## Why

The second half of T-06. `spotify-playback-api` gave the server a token endpoint and a start endpoint. This change builds the player in the browser: Spotify's Web Playback SDK becomes a device inside the AeroPlayer tab, the Aero player bar shows what plays and drives it, and the music keeps playing while the owner moves between pages. It is the first time AeroPlayer plays music, and T-07, T-08, T-13 and T-14 start playback through the `play()` function it adds.

## What Changes

- A persistent Inertia layout holds the player bar on every signed-in page (dashboard, styleguide and Spotify settings), so navigating never recreates the player.
- **With Spotify connected:**
  - the layout loads the SDK and creates one device named after the app (`APP_NAME`);
  - the SDK gets its token from `GET /spotify/token`;
  - the controls stay disabled until the device is ready.
- **Without a connection:** the SDK is not loaded, and the bar shows "Connect Spotify to play your music." with a link to the settings page.
- **The bar shows the current track:** title, artists, cover, elapsed and total time. The progress is interpolated while playing and stops when paused. "Next" is disabled when there is no next track.
- **Controls:**
  - pause, resume, seek, volume, next and previous go through the SDK;
  - when the browser blocks autoplay, the bar shows "Press play to start listening".
- **The volume** is remembered in the browser (owner's decision), starting at 50 %.
- **`play({contextUri?, uris?, offset?})`** starts playback through `PUT /spotify/player/play` after `activateElement()`. It rejects when neither or both of `contextUri` and `uris` are given.
- **Leaving:** logging out or disconnecting Spotify disconnects the device. A 401 or a 419 from the endpoints sends the owner to sign in.
- **Tests:** a fake SDK, served only in the tests, drives the browser tests.

## Non-goals

- Playback moving to another device, two tabs and the SDK's error events (T-23).
- The queue, shuffle and repeat (T-13); search and library screens (T-07, T-08).
- The Aero navigation (T-25), keyboard shortcuts and the Media Session API.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `spotify-playback`: adds the browser player. That covers the SDK device, the token callback, the bar's state and progress, the controls, the remembered volume, `play()`, playback across navigation and disconnecting on leave.

## Impact

- **New:**
  - `resources/js/layouts/PlayerLayout.vue`;
  - `resources/js/composables/useSpotifyPlayer.ts`;
  - `tests/Browser/fixtures/fake-spotify-sdk.js`;
  - `tests/Browser/SpotifyPlayerTest.php`.
- **Changed:**
  - `resources/js/app.ts` gives signed-in pages the persistent layout by default;
  - `AeroPlayerBar` reports the volume as "N%";
  - `HandleInertiaRequests` shares the SDK URL;
  - `config/services.php` gets `spotify.sdk_url`, which `phpunit.xml` leaves empty so no test loads the real SDK;
  - the layout shortens the kit's sidebar on the dashboard so the bar never covers it, until T-25;
  - `tests/Browser/SpotifySettingsTest.php` counts its own panel only.
- **Manual check** with real Premium audio, recorded in design.
- **Backlog:** closes T-06.
