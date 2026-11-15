# Tasks

> Backlog task: **T-09** (part 2 of 2; closes it). Requirements: `specs/youtube-music-credentials` and `specs/youtube-music-connection` (delta). Approach: `design.md`.
>
> Every test uses `Http::preventStrayRequests()` and `Http::fake()`: no test reaches Google. On Windows, run the tests with `php vendor/bin/pest`, with `PHPRC` and `PHP_INI_SCAN_DIR` set. Run browser tests under `timeout 90`, and check first that no `public/hot` file exists.

## 1. Data model

- [x] 1.1 Add the migration with the nullable `config_error_at` on `youtube_music_connections`, its cast on the model, and its reset in `storeForOwner()` (design D4). Add `storeRefreshedTokens()`, `markReconnectRequired()` and `markConfigError()` to `YouTubeMusicConnectionRepository`, with `#[\SensitiveParameter]` on the token arguments. Verify in `tests/Feature/YouTubeMusic/YouTubeMusicConnectionRepositoryTest.php`:
  - stored renewed tokens are encrypted, with the new expiry;
  - a null refresh token keeps the stored one;
  - storing renewed tokens clears the mark;
  - marking for reconnecting nulls the tokens and the expiry;
  - the mark is set once and keeps its first time;
  - each method leaves a connection with another `connection_id` alone;
  - `storeForOwner()` clears the mark;
  - the token parameters are sensitive.

## 2. Credentials service

- [x] 2.1 Add `GoogleDeviceFlow::requestRefresh()` (design D2) and the four exceptions under `YouTubeMusicCredentialsFailed` (design D3). Verify in `GoogleDeviceFlowTest`:
  - the exact URL and form fields of the renewal;
  - no `->throw()`: a 503 comes back as the response;
  - the parameter is sensitive.
- [x] 2.2 Build `App\YouTubeMusic\YouTubeMusicCredentials` (design D1 and D2) and add it to the entry-point rule in `ArchitectureTest`. Verify with `tests/Feature/YouTubeMusic/YouTubeMusicCredentialsTest.php`, with a frozen clock:
  - "No connection" and "Connection waiting for a reconnect";
  - "Token valid for an hour";
  - "Token expired a minute ago", including the reconnect notice absent from the settings props, and "Google rotates the refresh token";
  - "The authorization expired or was revoked", including the settings page;
  - "Missing tokens";
  - "Tokens that no longer decrypt", by writing a token encrypted with another key straight into the table;
  - "The renewal fails for a moment", as a dataset of 503, 429 and a `ConnectionException`;
  - "The client secret changed" and "The client is no longer configured";
  - "The secret is fixed".

  Verify also that `ArchitectureTest` passes.
- [x] 2.3 Verify "Two requests at once" and "Another renewal never ends" in `YouTubeMusicCredentialsTest`, in a single process like `SpotifyClientTest` (design D2):
  - a faked lock whose `block()` first stores "the other request's" fresh token: the service returns it and sends no renewal;
  - a faked lock whose `block()` first deletes the connection, or marks it `reconnect_required`: the request fails as "not connected" or "reconnect required", and nothing is sent (design D2, the re-read);
  - a lock held from the test, with `Sleep::fake(syncWithCarbon: true)`: the request fails as "unavailable" and nothing is sent.
- [x] 2.4 Verify "A failed renewal logs no secret" in `tests/Feature/YouTubeMusic/YouTubeMusicLogsTest.php`. The test listens to `MessageLogged` and checks the failure's message as well.

## 3. Settings page

- [x] 3.1 Add `misconfigured` to `GetYouTubeMusicSettings` and its result, the controller prop and the page message (design D5), following the `frutiger-aero` skill. Verify in `YouTubeMusicSettingsTest`:
  - "A renewal was refused for the client" and "No configuration error";
  - the mark is hidden while the client is not configured.

  Then verify that `npm run types` and `npm run build` exit 0.
- [x] 3.2 Add a "configuration error" state to the dataset of `tests/Browser/YouTubeMusicSettingsTest.php`, so the Aero, focus and 375 px checks cover it, and check that the page shows the message. Verify under `timeout 90`.

## 4. Docs and integration

- [x] 4.1 Add a paragraph to the README's "YouTube Music" section. Verify that it names no real ids or secrets. It covers:
  - the token renewal;
  - the reconnect notice after the 7 days in "Testing";
  - "YouTube Music is not configured correctly." after a secret change.
- [x] 4.2 Run Pint, Prettier and ESLint on the changed files. Verify that `npm run types`, `npm run build` and the full `vendor/bin/pest` run pass together, with the browser suite split in two halves.
- [x] 4.3 Manual check with the owner's real connection, in the local app:
  - connect YouTube Music;
  - set `access_token_expires_at` in the past, then run `php artisan tinker --execute='app(App\YouTubeMusic\YouTubeMusicCredentials::class)->accessToken();'`. Use `--execute` without `echo`: interactive tinker prints the returned token;
  - check that the stored expiry moved about an hour ahead and the status is still `connected`.

  Record the date and the result in design "Manual checks". No token goes into the repository or the chat.

## Workflow follow-up

- `/review-change youtube-music-credentials` before `/opsx-apply`.
- At `/ship`:
  - archive the change and close T-09;
  - update T-12 with the service's name and its four failures.
