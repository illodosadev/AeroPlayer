# Tasks

Browser tests need `npm run build` first. Run them one file at a time with a timeout, and never walk Tab past the last enabled control. No test reaches Spotify: the SDK is the fake from design D12, and `api.spotify.com` is faked with `Http::fake()`.

## 1. Groundwork

- [x] 1.1 Add `services.spotify.sdk_url`, set `SPOTIFY_SDK_URL` to an empty value in `phpunit.xml`, and share the URL as `spotify.sdkUrl` for signed-in users (design D11). Verify in `tests/Feature/Spotify/SpotifySettingsTest.php` that the owner's pages share `spotify.sdkUrl` and guests do not.
- [x] 1.2 Write `tests/Browser/fixtures/fake-spotify-sdk.js`, and the `beforeEach` of `tests/Browser/SpotifyPlayerTest.php` that registers its route, points `sdk_url` at it, fakes `api.spotify.com` and clears `aeroplayer.volume` from `localStorage` (design D12). Add one smoke test that loads the fixture on a blank page and checks that `window.Spotify.Player` exists.

## 2. The player

- [x] 2.1 Create `PlayerLayout.vue` and give it to every non-`auth/` page in `app.ts` (design D1). It shows the bar, with the "Connect Spotify…" notice when Spotify is not connected (design D10). Scope the glass-panel count in `tests/Browser/SpotifySettingsTest.php` to `#spotify-settings` (design D13). Verify:
  - "The player bar is on every signed-in page";
  - "Spotify not connected";
  - `AeroPlayerBarTest`, `AeroAccessibilityTest`, `SpotifySettingsTest` and `OwnerAuthBrowserTest` pass. `OwnerAuthBrowserTest` must pass unchanged, which proves that the bar does not cover the kit sidebar's user menu (design D1).
- [x] 2.2 Create `useSpotifyPlayer.ts`, covering loading, the player, the token callback, readiness, the state mapping and the progress (design D2, D3, D4, D6, D7), and wire it into the layout. Verify in `SpotifyPlayerTest.php`:
  - "The SDK player is created with Spotify connected";
  - "The SDK asks the backend for a token";
  - "Controls disabled until the device is ready";
  - "The bar shows the current track";
  - "No next track";
  - "The progress advances while playing";
  - "The progress stops while paused";
  - with Spotify connected and the empty SDK URL of `phpunit.xml`, no SDK script is requested and no player is created. Check this in the browser `SpotifySettingsTest`, whose tests create a connection (design D3, D11).
- [x] 2.3 Add the controls, blocked autoplay, the remembered volume and the volume value text in `AeroPlayerBar` (design D8, D9). Verify:
  - "Pause and resume";
  - "Change the volume";
  - "Seek within the track";
  - "Next and previous within the context";
  - "Previous restarts a track after 3 seconds", added after the manual check of step 4.1;
  - "The browser blocks autoplay";
  - "The volume survives a reload".
- [x] 2.4 Add `play()` and `window.aeroPlayer` (design D4, D5). Verify the three rows of "Starting playback from the player function", with `Http::assertSent` on the server side and the call order in `__fakeSpotify.calls`. Also verify "The player function rejects invalid arguments".
- [x] 2.5 Add stopping and the session redirects (design D4, D10). Verify:
  - "The music keeps playing across pages";
  - "Logging out stops the music";
  - "Disconnecting Spotify with music playing";
  - "An expired session sends the owner to sign in". The session is ended in the background with a `fetch` POST to `/logout` from the page.

## 3. Integration

- [x] 3.1 Run Prettier and ESLint on the changed frontend files, and Pint on the changed PHP files. Then verify that `npm run types`, `npm run build`, `openspec validate spotify-web-player --strict` and the full `vendor/bin/pest` run pass together.

## 4. Manual check with real Spotify Premium

- [x] 4.1 With the owner's real Spotify Premium on `http://127.0.0.1:8000`, in desktop Chrome:
  - a device named like `APP_NAME` appears in the official Spotify app's device list;
  - `window.aeroPlayer.play({contextUri: '<a real album URI>'})` plays the first track in the tab, and the bar matches it;
  - Pause, Play, volume at 30 %, a seek to 1:30, Next and Previous are heard as expected. Note what Previous does before and after the first 3 seconds of a track.

  Record the date and the results in design "Manual checks", with nothing from the account.

## Workflow follow-up

- `/review-change spotify-web-player` before `/opsx-apply`.
- At `/ship`:
  - merge the `spotify-playback` delta;
  - set T-06 to `status: done`;
  - apply the backlog follow-ups from design (T-07, T-08, T-13, T-16, T-17, T-19, T-23, T-25).
