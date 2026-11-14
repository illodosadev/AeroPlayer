# Tasks

> Backlog task: **T-05** (part 1 of 2). Requirements: `specs/spotify-connection` and `specs/owner-auth` (deltas). Approach: `design.md`.
>
> Every test uses `Http::preventStrayRequests()` and `Http::fake()`: no test reaches Spotify. On Windows, run the tests with `php vendor/bin/pest`, with `PHPRC` and `PHP_INI_SCAN_DIR` set. Run new browser tests one at a time under `timeout 90`.

## 1. Configuration and data model

- [x] 1.1 Add the `spotify` block to `config/services.php` (design D6), and the four `SPOTIFY_*` keys to `.env.example` with the local redirect URI. Add `App\Spotify\SpotifyScopes` with `REQUIRED` and `missing()`. Add a unit test for `missing()`.
- [x] 1.2 Create the `spotify_connections` migration, the `SpotifyConnection` model and its factory (design D3):
  - `encrypted` casts on both tokens, which are also `$hidden`;
  - the `SpotifyConnectionStatus` enum;
  - a unique `user_id` with cascade on delete.

  Verify that the migration runs, and that the "Tokens are stored encrypted" scenario passes in `SpotifyConnectionRepositoryTest`.
- [x] 1.3 Build `SpotifyConnectionRepository`, with `current()`, `status()`, `storeForOwner()` and `deleteForOwner()`, and the `SpotifyAccount` and `SpotifyTokens` value objects (design D4). Test the following in `SpotifyConnectionRepositoryTest`:
  - one row after two stores;
  - the tokens replaced;
  - a new `connection_id`;
  - status `connected` after `reconnect_required`;
  - the cascade when the user is deleted.

## 2. OAuth flow

- [x] 2.1 Build `App\Spotify\SpotifyOAuth` (design D1, D2):
  - `isConfigured()`, `authorizeUrl(string $state)`, `exchangeCode()` and `fetchAccount()`;
  - the `ConnectFailure` enum with its messages;
  - no `->throw()`, a 10 s timeout, and `ConnectionException` mapped to `Unavailable`.
- [x] 2.2 Add `SpotifyConnectionController@redirect` with the `spotify.connect` route, and `GET /settings` as a redirect to `/settings/spotify` (design D7). Later steps register the route of the handler they build. Then add `tests/Feature/Spotify/SpotifyConnectTest.php`:
  - "Starting the connection redirects to Spotify with the scopes and a state", parsing `Location`;
  - "A Spotify setting is missing", with a dataset of the three variables: the redirect to `/settings/spotify` and `Http::assertNothingSent()`. The page text is checked in 3.1;
  - "Opening the settings".
- [x] 2.3 Add `SpotifyConnectionController@callback` and the `spotify.callback` route: state check first, with `pull` and `hash_equals` (design D5), then cancellation, exchange, account lookup, owner and Premium checks, the store, and the flash message (design D8). Then add `tests/Feature/Spotify/SpotifyCallbackTest.php` with:
  - "Callback without a session";
  - "Connecting the owner's account";
  - "Tampered state", "Missing state" and "Repeated callback";
  - "The owner cancels on Spotify's page" and "Cancellation with a wrong state";
  - "Another Spotify account is rejected" and "Another account leaves the owner's connection untouched";
  - "The owner's account without Premium";
  - "Account missing from the Spotify app's user list";
  - "Expired or used code";
  - "The token endpoint answers 503" and "The account lookup cannot be reached", with `Http::fake()` throwing a `ConnectionException` for the second;
  - "Reconnecting the same account" and "Reconnecting clears the reconnect-required status";
  - "A failed code exchange logs no secret", using `Event::listen(MessageLogged::class)`.

## 3. Settings page and shared props

- [x] 3.1 Add `SpotifySettingsController@show` (design D9) with the `spotify.settings` route, and `SpotifyConnectionController@destroy` with the `spotify.disconnect` route. Add the `spotify` shared props to `HandleInertiaRequests` (design D10). Then add `tests/Feature/Spotify/SpotifySettingsTest.php` with:
  - "Not connected";
  - "Connected with the reconnect date", using `travelTo` 2026-11-11;
  - "Reconnect date at the end of a month", authorized on 2026-08-31 (design D3);
  - "Access expired or revoked";
  - "Scopes missing after the app asks for more permissions";
  - "Disconnecting Spotify" and "Disconnecting without a connection";
  - "Shared status for each connection state";
  - "The settings page props hold no tokens", serialising the whole Inertia page;
  - the "not configured" page state: "Spotify is not configured." and no "Connect Spotify" button.
- [x] 3.2 Build `resources/js/pages/settings/Spotify.vue` with Aero components (design D11), following the `frutiger-aero` skill:
  - the message region with `role="status"`;
  - the state texts;
  - the GET form for "Connect Spotify";
  - `router.delete` for "Disconnect Spotify";
  - the "Back" link with a 44 px target.

  Add the shared `spotify` prop to the TypeScript types. Verify that `npm run types` and `npm run build` exit 0.
- [x] 3.3 Add the "Settings" item to `UserMenuContent.vue`, linking to `/settings/spotify`. Update the tests for the `owner-auth` delta:
  - remove `/settings` from the 404 dataset in `OwnerAuthTest`;
  - in `OwnerAuthBrowserTest`, expect a "Settings" link to `/settings/spotify` and none to the removed settings pages.

  Verify that both files pass.
- [x] 3.4 Move the accessibility helpers to `tests/Pest.php` (design D12): `AERO_CONTROLS`, `AERO_KEY`, `AERO_INDICATOR`, `aeroAssertFocusWalk()` and `AERO_MOBILE_PROBLEMS`. `aeroOwnerAt()` stays in `AeroAccessibilityTest.php`. Name the moved helpers in "Testing hooks" of `.claude/skills/frutiger-aero/SKILL.md`. Verify that `AeroAccessibilityTest` still passes, under `timeout 90`.
- [x] 3.5 Add `tests/Browser/SpotifySettingsTest.php` with the scenarios of "The Spotify settings page uses the Aero look":
  - "The settings page is Aero": background, glass panel and no `data-slot`;
  - "The settings page shows keyboard focus", with `aeroAssertFocusWalk()`, which only counts the enabled controls;
  - "The settings page fits a 375 px phone", with `AERO_MOBILE_PROBLEMS`.

  Run it under `timeout 90`. Verify that it passes.

## 4. Docs

- [x] 4.1 Add a "Spotify" section to the README:
  - register the app in the Spotify Developer Dashboard, in development mode, with the owner's Premium account;
  - the two redirect URIs, using placeholders for the production domain;
  - the four `SPOTIFY_*` variables, and how to find `SPOTIFY_OWNER_ID` (the `id` of `GET /me`, or the profile URL);
  - open the app on `127.0.0.1`, not `localhost`;
  - keep `SESSION_SAME_SITE=lax`;
  - the 6-month reconnect;
  - the manual check list from 6.1.

  No real ids or secrets.

## 5. Integration

- [x] 5.1 Run Pint, Prettier and ESLint on the changed files. Then verify that `npm run types`, `npm run build` and the full `vendor/bin/pest` run pass together.

## 6. Manual check with the real Spotify app

- [x] 6.1 With the owner's Spotify app registered and `.env` filled in locally, on `http://127.0.0.1:8000`:
  - connect, and check that the page shows "Spotify connected", the name and "Reconnect before <date>";
  - check that the tokens are not in the page source;
  - disconnect;
  - connect again.

  Record the date and the result in design "Manual checks". Nothing from the account goes into the repository.

## Workflow follow-up

- `/review-change spotify-connection` before `/opsx-apply`.
- At `/ship`:
  - apply the backlog follow-ups from design (T-05, T-04, T-08, T-06, T-14, T-18, T-25);
  - leave T-05 open for `spotify-api-client`;
  - archive the change.
