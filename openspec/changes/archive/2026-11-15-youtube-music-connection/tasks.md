# Tasks

> Backlog task: **T-09** (part 1 of 2; `youtube-music-credentials` closes it). Requirements: `specs/youtube-music-connection` and `specs/spotify-connection` (delta). Approach: `design.md`.
>
> Every test uses `Http::preventStrayRequests()` and `Http::fake()`, with fixtures in `tests/Fixtures/google/`: no test reaches Google. On Windows, run the tests with `php vendor/bin/pest`, with `PHPRC` and `PHP_INI_SCAN_DIR` set. Run new browser tests one at a time under `timeout 90`, and check first that no `public/hot` file exists (Vite running stalls them).

## 1. Configuration and data model

- [x] 1.1 Add the `youtube_music` block to `config/services.php` and the three empty `YOUTUBE_MUSIC_*` keys to `.env.example` (design D10). Verify with a unit test that YouTube Music counts as configured only when the client id, the secret and the owner channel id are all set.
- [x] 1.2 Create the `youtube_music_connections` migration, the `YouTubeMusicConnection` model with its factory, and the `YouTubeMusicConnectionStatus` enum (design D5): `encrypted` and `$hidden` tokens, a unique `user_id` with cascade on delete. Verify in `tests/Feature/YouTubeMusic/YouTubeMusicConnectionRepositoryTest.php` that the migration runs and that "Tokens are stored encrypted" passes.
- [x] 1.3 Build `YouTubeMusicConnectionRepository` with `current()`, `status()`, `storeForOwner()` and `deleteForOwner()`, plus the value objects for the tokens and the channel (design D5). Verify in the same test file:
  - "Reconnecting replaces the connection": one row, the new refresh token and a new `connection_id`;
  - the status `connected` after `reconnect_required`;
  - the cascade when the user is deleted.

## 2. Google device flow service

- [x] 2.1 Build `App\YouTubeMusic\GoogleDeviceFlow` (design D1): `isConfigured()`, `requestDeviceCode()`, `pollToken()`, `fetchChannel()` and `revoke()`, plus the `YouTubeMusicConnectFailure` enum with its statuses and messages (design D3). It never calls `->throw()`, uses a 10 s timeout and `#[\SensitiveParameter]`, and maps `ConnectionException`. Verify with `tests/Feature/YouTubeMusic/GoogleDeviceFlowTest.php`, using `Http::fake()` sequences and a `ConnectionException`:
  - every token answer maps correctly: pending, `slow_down`, 429, 5xx, timeout, `access_denied`, `expired_token`, `invalid_client`, any other refusal, success;
  - the channel lookup returns the owner, another channel, no channel, an error or a timeout;
  - the exact URLs and form fields of each request.

## 3. Starting and waiting for approval

- [x] 3.1 Add the `StartYouTubeMusicConnection` command and handler, with the attempt entry in the cache (design D2) and `YouTubeMusicConnectionFailed`. Add `StartYouTubeMusicConnectionController` and its route (design D7). Verify with `tests/Feature/YouTubeMusic/StartYouTubeMusicConnectionTest.php`:
  - "Starting the connection": one request, the client id and scope, and the redirect to `/settings/youtube-music`. The page itself is checked in 4.1;
  - "Connect requested while connected";
  - "A YouTube Music setting is missing", as a dataset of the three keys: the redirect and `Http::assertNothingSent()`;
  - "Google does not answer the device code request" (503, 429 and `ConnectionException`) and "Google rejects the client": the flashed message, checked in the session;
  - "Starting again": the second code replaces the first;
  - the device code stored encrypted, reading the `cache` table directly;
  - "A visitor opens the YouTube Music settings", for `POST /youtube-music/connect` too.
- [x] 3.2 Add the `CheckYouTubeMusicApproval` command and handler with the pacing, the lock and the owner check (design D3, D4 and D6). Add the `GetYouTubeMusicConnectStatus` query, `ShowYouTubeMusicConnectStatusController` and its route, and the JSON 401 path in `bootstrap/app.php`. Verify with `tests/Feature/YouTubeMusic/YouTubeMusicApprovalTest.php`, using `freezeSecond()` and `travel()` and counting the calls to Google:
  - "The server respects Google's interval", "Google asks to slow down" and "The token endpoint fails while waiting";
  - "Connecting the owner's account";
  - "Another account approves the code", as a dataset of other channel and no channel;
  - "The channel cannot be checked";
  - "The owner denies access" and "The code expires", as a dataset of `expired_token` and time passed;
  - "Google rejects the client while waiting" and "Google refuses for another reason";
  - "A failed attempt leaves the connection alone";
  - "No attempt in progress";
  - "Starting again": no poll carries `dev-123`;
  - "The app session expires while waiting for approval": a JSON 401;
  - `Cache-Control: no-store, private` on the status response.
- [x] 3.3 Verify "Two status requests at once" in `YouTubeMusicApprovalTest`, in a single process, like the Spotify client's lock tests (`clientLockWhereAnotherCallRefreshes()` in `SpotifyClientTest`):
  - **the re-read inside the lock:** fake the lock so that `block()` first plays "the other request", which stores the connection and removes the attempt, then runs the handler's callback. The status answers `connected`, no request reaches the token endpoint, and there is exactly one connection;
  - **a lock that never frees:** take the lock from the test as "another process", and use fake sleeps so the wait passes instantly. The status answers `pending` without error, and nothing is sent to Google.

## 4. Settings page, link bar, disconnect and shared prop

- [x] 4.1 Add the `GetYouTubeMusicSettings` query with its `YouTubeMusicSettings` result, `ShowYouTubeMusicSettingsController` and its route (design D7). Add the `GetYouTubeMusicStatus` query and the `youtubeMusic` shared prop (design D10). Add `App\YouTubeMusic\GoogleDeviceFlow` to the rules in `ArchitectureTest`. Verify with `tests/Feature/YouTubeMusic/YouTubeMusicSettingsTest.php`:
  - "Not connected", "Connected" and "Access expired or revoked";
  - "A YouTube Music setting is missing": "YouTube Music is not configured." and no "Connect YouTube Music" button;
  - "Starting the connection": the waiting page shows "ABCD-EFGH", the address and the waiting text, and neither the page nor its props contain `dev-123` or the secret;
  - every final message from design D3 appears after its status, including "Couldn't reach Google. Try again in a moment." and "YouTube Music is not configured correctly." from 3.1;
  - "Shared status for each connection state";
  - "The settings page holds no tokens", serialising the whole page and the status response;
  - "A visitor opens the YouTube Music settings", for the GET.

  Add the three routes built so far (`youtube-music.settings`, `youtube-music.connect` and `youtube-music.connect.status`) to the route list in `RoutesTest`, with the `web` and `auth` middleware. Verify that `ArchitectureTest` and `RoutesTest` pass.
- [x] 4.2 Add the `DisconnectYouTubeMusic` command and handler, `DisconnectYouTubeMusicController` and its route (design D6). Add `youtube-music.disconnect` to `RoutesTest`. Verify in `YouTubeMusicSettingsTest`:
  - "Disconnecting YouTube Music";
  - "Disconnecting while Google does not answer" (503 and `ConnectionException`);
  - "Disconnecting without a connection";
  - "A visitor opens the YouTube Music settings", for the DELETE.

  Also verify that `RoutesTest` passes.
- [x] 4.3 Verify "Failures log no secret" in `tests/Feature/YouTubeMusic/YouTubeMusicLogsTest.php`, with `Event::listen(MessageLogged::class)`, over the three failures of the scenario.
- [x] 4.4 Build `resources/js/components/settings/SettingsNav.vue` (design D9) and use it in `settings/Spotify.vue`. Build `resources/js/pages/settings/YouTubeMusic.vue` (design D8), following the `frutiger-aero` skill:
  - the states and the message region with `role="status"`;
  - the large user code and the address opening in a new tab with `rel="noopener"`;
  - polling every 2 s that stops on a final status, on unmount and on a 401;
  - `router.post` and `router.delete`.

  Add the `YouTubeMusicStatus` type and the `youtubeMusic` shared prop to `resources/js/types/index.ts`. Verify that `npm run types` and `npm run build` exit 0.
- [x] 4.5 Add `tests/Browser/YouTubeMusicSettingsTest.php`:
  - "The page is Aero in every state", "The page shows keyboard focus" (with `aeroAssertFocusWalk()`) and "The page fits a 375 px phone" (with `AERO_MOBILE_PROBLEMS`), each for not connected, waiting, connected and reconnect required;
  - "Going back to the Spotify settings";
  - polling: with a faked attempt the owner approves, the page moves from waiting to "YouTube Music connected" without a manual reload.

  In `tests/Browser/SpotifySettingsTest.php`, add "Reaching the YouTube Music settings" and "The current page is marked", and check that the existing focus and 375 px checks still pass with the link bar. Verify both files under `timeout 90`.

## 5. Docs

- [x] 5.1 Add a "YouTube Music" section to the README (design D10). Verify that it holds no real ids or secrets. It covers:
  - creating the Google Cloud project, enabling the YouTube Data API v3 and creating a "TV and Limited Input devices" OAuth client;
  - adding the owner as a test user, and the 7-day limit while the app stays in "Testing";
  - the three `YOUTUBE_MUSIC_*` variables, and how to find the channel id;
  - the manual check list from 6.2.

## 6. Integration

- [x] 6.1 Run Pint, Prettier and ESLint on the changed files. Verify that `npm run types`, `npm run build` and the full `vendor/bin/pest` run pass together. Split the browser suite in two halves if a single run exceeds the time limit.
- [x] 6.2 Manual check with the owner's real Google client, in the local app:
  - connect, approve the code with the owner's account and check that the page shows "YouTube Music connected" and the channel name;
  - check that the page source holds no token or device code;
  - disconnect, and check that the access is gone from myaccount.google.com/permissions;
  - connect again.

  Record the date and the result in design "Manual checks". Nothing from the account goes into the repository.

## Workflow follow-up

- `/review-change youtube-music-connection` before `/opsx-apply`.
- At `/ship`: leave T-09 open for `youtube-music-credentials`, and archive the change.
