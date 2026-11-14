# Design

## Context

See proposal.md (Why). Backlog task T-05 holds the research behind this design: the development-mode rules of February 2026, the redirect URI rules, token lifetimes and the scope list.

Current state:

- **Auth:** one owner account (`owner-auth`). Every protected route uses the `auth` middleware. There are no settings routes: single-owner-login removed the kit's settings pages and layout. The `owner-auth` spec requires "/settings" to return 404 and the user menu to have no "Settings" link; this change modifies that requirement.
- **Data access:** every model query goes through a concrete class in `app/Repositories` (AGENTS.md). Only `UserRepository` exists today.
- **Infrastructure:**
  - sessions and cache use the `database` driver locally; production uses `database` too (T-04);
  - the test suite is self-contained (`app-foundation`), so no test may reach Spotify.
- **Look:** Aero components and the `frutiger-aero` skill (`aero-design`). New pages are Aero, with no kit (`data-slot`) components.
- **Spotify:**
  - the redirect URI must match exactly, and `localhost` is not allowed (only `127.0.0.1`);
  - `GET /me` no longer returns `product` in development mode;
  - refresh tokens live 6 months from authorization.

## Goals / Non-Goals

**Goals**

- A connection model and repository that the second change (`spotify-api-client`) can build on without schema changes: tokens, expiry, status and identifier are all here.
- An OAuth flow that never logs or exposes secrets and never turns a Spotify failure into a 500.

**Non-Goals**

- Refreshing tokens or calling the Web API after the connection. That belongs to `spotify-api-client`, which also sets `reconnect_required`.
- PKCE on top of the client secret: the secret stays on the server, and Spotify recommends the plain authorization code flow for that case.

## Decisions

### D1. Laravel's HTTP client, no Socialite

The flow is two requests: `POST https://accounts.spotify.com/api/token` with `grant_type=authorization_code` and HTTP Basic client credentials, then `GET https://api.spotify.com/v1/me` with the new access token. Both are built in `App\Spotify\SpotifyOAuth` with `Http::asForm()->withBasicAuth()` and `timeout(config('services.spotify.timeout'))`, which is 10 s.

The authorize URL is built by hand from `client_id`, `redirect_uri`, `response_type=code`, the space-separated scopes, `state` and `show_dialog=true`.

*Alternative rejected:* Laravel Socialite with the SocialiteProviders Spotify driver.

- It adds two packages and keeps its own state handling, which would have to match the spec's single-use state and messages.
- The refresh, the status and the shared client must be our own code anyway.

### D2. One service, typed outcomes, no thrown HTTP exceptions

`SpotifyOAuth::exchangeCode()` and `fetchAccount()` never call `->throw()`. Laravel's `RequestException` message embeds part of the response body. Instead they inspect the status and map it to a small enum `ConnectFailure`:

| Outcome | Message |
|---------|---------|
| `Cancelled` | "Spotify connection was cancelled." |
| `InvalidState` | "Could not connect Spotify. Please try again." |
| `ExchangeRejected` (4xx on the token endpoint) | "Could not connect Spotify. Please try again." |
| `NotAllowed` (403 on `/me`) | "This Spotify account is not allowed to use this app." |
| `NotOwner` | "Only the owner's Spotify account can be connected." |
| `NotPremium` | "Spotify Premium is required." |
| `Unavailable` (5xx, 429, `ConnectionException`) | "Spotify is not responding right now. Try again in a moment." |

The controller turns the outcome into a flash message and a redirect. A `ConnectionException` is caught and mapped, never rethrown. Nothing in this path writes to the log.

The spec's "secrets are not logged" scenario guards this. The test listens to `MessageLogged` and checks that no entry contains the code or the secret.

*Alternative rejected:* exceptions per failure, caught in the controller. That is more classes for the same mapping, and an uncaught one would reach the 500 handler.

### D3. Data model

The `spotify_connections` table:

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | |
| `user_id` | foreign key to `users` | unique, cascade on delete |
| `connection_id` | uuid | regenerated on every successful connect |
| `spotify_user_id` | string | |
| `display_name` | string, nullable | |
| `scopes` | text | space-separated, as Spotify returns them |
| `access_token` | text, nullable | `encrypted` cast |
| `refresh_token` | text, nullable | `encrypted` cast |
| `access_token_expires_at` | timestamp, nullable | |
| `authorized_at` | timestamp | drives "Reconnect before" |
| `status` | string | `connected` or `reconnect_required`, a backed enum `SpotifyConnectionStatus` |
| timestamps | | |

- **One row:** the unique `user_id` plus the single owner make "at most one connection" structural.
- **Hidden fields:** the model hides `access_token` and `refresh_token` (`$hidden`), so even an accidental `toArray()` cannot send them.
- **Reconnect before:** the date is `authorized_at->copy()->addMonthsNoOverflow(6)`, rendered as `Y-m-d`. Plain `addMonths` would turn 2026-08-31 into 2027-03-03, past Spotify's limit; the no-overflow version gives 2027-02-28.

*Alternatives rejected:*

- A generic `oauth_connections` table for Spotify and YouTube Music. T-09 has not been designed, and its token shape may differ.
- Storing the tokens in `users`. That couples auth to Spotify, and deleting the connection would mean nulling columns.

### D4. `SpotifyConnectionRepository`

A concrete class, injected:

- `current(): ?SpotifyConnection`: the owner's connection or null.
- `status(): SpotifyConnectionStatus|null`: for the shared props, so the page does not load the tokens.
- `storeForOwner(User $owner, SpotifyAccount $account, SpotifyTokens $tokens): SpotifyConnection`: an `updateOrCreate` on `user_id`. It sets the tokens, scopes, expiry, `authorized_at = now()`, status `connected` and a new `connection_id`.
- `deleteForOwner(User $owner): void`.

`SpotifyAccount` (id, display name, product) and `SpotifyTokens` (access, refresh, expires in, scope) are small readonly value objects returned by `SpotifyOAuth`.

The second change adds `saveRefreshedTokens()` and `markReconnectRequired()` here.

### D5. State handling

- `GET /spotify/connect` puts `Str::random(64)` in the session under `spotify_oauth_state` and redirects to the authorize URL.
- The callback reads the state with `$request->session()->pull('spotify_oauth_state')`, so it is gone after the first callback, successful or not. It compares the state with `hash_equals`.
- The state is always checked first. A missing or different state ends the callback before any HTTP call with `InvalidState`, even when the callback carries `error=access_denied`.
- Only a matching state with `error=access_denied` answers `Cancelled`.

### D6. Configuration

`config/services.php` gains a `spotify` block:

- `client_id`, `client_secret` and `owner_id`;
- `redirect_uri`, which defaults to `url('/spotify/callback')`;
- `timeout`, 10.

`SpotifyOAuth::isConfigured()` is false when any of `client_id`, `client_secret` or `owner_id` is empty. The connect route then redirects to the settings page, which reads the same check and hides "Connect Spotify".

The required scopes are a constant list in `App\Spotify\SpotifyScopes::REQUIRED`. `SpotifyScopes::missing(string $granted)` returns the required scopes not granted, for the page's "Reconnect Spotify to grant the new permissions."

`.env.example` gets the four `SPOTIFY_*` keys, empty, and `SPOTIFY_REDIRECT_URI=http://127.0.0.1:8000/spotify/callback` for `composer dev`.

### D7. Routes and controllers

All routes sit in the `auth` group of `routes/web.php`, so "Callback without a session" comes from the existing middleware.

| Method and URI | Name | Handler |
|---|---|---|
| `GET /spotify/connect` | `spotify.connect` | `SpotifyConnectionController@redirect` |
| `GET /spotify/callback` | `spotify.callback` | `SpotifyConnectionController@callback` |
| `DELETE /spotify/connection` | `spotify.disconnect` | `SpotifyConnectionController@destroy` |
| `GET /settings` | `settings` | redirect to `spotify.settings` |
| `GET /settings/spotify` | `spotify.settings` | `SpotifySettingsController@show` |

`GET /settings` stops returning 404, which is why the `owner-auth` delta changes. The other removed settings routes stay 404.

Each route is registered in the tasks step that builds its handler, so no route points to a missing method. `GET /settings` is a plain redirect by URI and comes with the first step.

### D8. Messages after a redirect live in the page

The callback and the disconnect redirect with `->with('spotifyMessage', [...])`, a session flash holding a `tone` (`success`, `info` or `error`) and a `text`. `SpotifySettingsController@show` passes it to the page as a `message` prop. The page shows it in a `role="status"` region at the top of the Spotify panel.

The disconnect message carries the spotify.com/account/apps hint as a second line.

*Alternatives rejected:*

- A toast (`vue-sonner`). It is a kit component with `data-slot`, and T-25 replaces toasts with `AeroFlash`.
- A shared flash prop for every page. Only this page needs it today, and T-25 will build `AeroFlash`.

### D9. Settings page props

`SpotifySettingsController@show` sends only display data:

- `configured`;
- `status`;
- `accountName`;
- `reconnectBefore` (a `Y-m-d` string or null);
- `missingScopes` (a boolean);
- `message`.

There are no tokens, ids or scopes lists. "Spotify tokens stay off the pages" is tested by serialising the whole Inertia page, shared props included, and searching for the token strings.

### D10. Shared props

`HandleInertiaRequests::share()` gains:

- `'spotify' => fn () => [...]`, a lazy closure that calls `SpotifyConnectionRepository::status()` only for signed-in requests;
- `status` (`not_connected` when there is no row) and `connected`.

The repository is resolved from the container, as the convention requires. Guests get `not_connected` without a query.

### D11. The Aero settings page

`resources/js/pages/settings/Spotify.vue`:

- `AeroBackground` with a centred column and one `AeroGlassPanel` titled "Spotify";
- the message region;
- the state text;
- the buttons.

The buttons work as follows:

- **"Connect Spotify":** an `AeroButton type="submit"` inside a `<form method="get" action="/spotify/connect">`. It must be a full page navigation, not an Inertia visit, because the response is a cross-origin redirect to Spotify.
- **"Disconnect Spotify":** an `AeroButton` that calls `router.delete('/spotify/connection')`.

The page has a "Back" link to `/dashboard`, since there is no settings navigation yet (T-18, T-25). It is styled in its own scoped CSS with a 44 px target, as the notice links are.

The user menu (`UserMenuContent.vue`) gains a "Settings" item that links to `/settings/spotify`, as T-02's rule asks until T-25's Aero navigation.

### D12. Tests

All tests use `Http::preventStrayRequests()`, with `Http::fake()` for `accounts.spotify.com/api/token` and `api.spotify.com/v1/me`.

**Feature tests:**

- `tests/Feature/Spotify/SpotifyConnectTest.php`: the redirect, configuration and `/settings`.
- `tests/Feature/Spotify/SpotifyCallbackTest.php`:
  - the state cases, cancellation (including a wrong state) and the session requirement;
  - a successful connection;
  - reconnecting, including the new identifier and the `reconnect_required` reset;
  - another account, non-Premium, 403, `invalid_grant`, 503 and a connection error;
  - the logs.
- `tests/Feature/Spotify/SpotifySettingsTest.php`:
  - the page states, including "not configured", the reconnect date (`travelTo`), its end-of-month case and missing scopes;
  - disconnecting;
  - the shared props;
  - no tokens in the props.
- `tests/Feature/Spotify/SpotifyConnectionRepositoryTest.php`: the repository on its own, including the encryption.

**Browser test:** `tests/Browser/SpotifySettingsTest.php` covers the three scenarios of "The Spotify settings page uses the Aero look":

- "The settings page is Aero": the background, the glass panel and no `data-slot`;
- "The settings page shows keyboard focus": the focus walk;
- "The settings page fits a 375 px phone": the layout and the 44x44 px touch areas.

The focus walk and the 375 px checks reuse the helpers of `AeroAccessibilityTest.php`. They are defined inside that file today, so a new test file run on its own would not find them. They move to `tests/Pest.php`, next to `signedInOwnerVisit()` and `aeroContrastRatio()`: `AERO_CONTROLS`, `AERO_KEY`, `AERO_INDICATOR`, `aeroAssertFocusWalk()` and `AERO_MOBILE_PROBLEMS`. `aeroOwnerAt()` stays where it is, since only that file uses it. The `frutiger-aero` skill's "Testing hooks" names the moved helpers.

The page's requirement carries its own accessibility scenarios, so `aero-design`'s "currently /login and /styleguide" requirements stay unchanged.

**Existing tests updated:**

- `OwnerAuthTest`: `/settings` leaves the 404 dataset.
- `OwnerAuthBrowserTest`: the menu now has "Settings".

## Risks / Trade-offs

- **[`localhost` vs `127.0.0.1` cookies]** If the app is opened on `localhost` and the callback comes back to `127.0.0.1`, the session is lost and the callback redirects to login. → The README says to open the app on `127.0.0.1` and to set `APP_URL` to match.
- **[`SameSite`]** The callback is a top-level GET from Spotify, so the default `Lax` session cookie travels with it. → Keep `SESSION_SAME_SITE=lax`. The README notes that `strict` breaks the callback.
- **[`APP_KEY` rotation makes the tokens unreadable]** The settings page never decrypts the tokens (D9), so it keeps working. Handling a decrypt failure during API calls belongs to `spotify-api-client`. `APP_PREVIOUS_KEYS` allows rotation.
- **[`product` is rarely present in development mode]** The Premium check seldom fires. Development mode itself requires the app owner to have Premium, and only the owner's account is accepted.
- **[The redirect URI must match exactly]** → The README lists both URIs to register: local `http://127.0.0.1:8000/spotify/callback`, and production `https://music.<domain>/spotify/callback` once T-04 deploys.

## Migration Plan

1. A new migration for `spotify_connections`. There is no data to move.
2. Deploy: run the migration and set the four `SPOTIFY_*` variables. The production redirect URI is checked when T-04 ships.
3. Rollback: roll the migration back and revert the commits. Spotify access can then be removed at spotify.com/account/apps.

## Open Questions

None. The production redirect check is a T-04 follow-up, recorded at `/ship`.

## Manual checks

2026-11-14, step 6.1, with the owner's real Spotify app on `http://127.0.0.1:8000`: passed.

- Connecting showed "Spotify connected.", the account name and "Reconnect before" six months later.
- The stored tokens were encrypted, no required scope was missing, and the page source held no token.
- Disconnecting showed "Spotify disconnected." with the spotify.com/account/apps note and the connect button again.
- Connecting again showed "Spotify connected" again.

The first attempt showed "Spotify is not responding right now." because the local PHP had no root certificates for HTTPS (cURL error 60). The app handled it as designed. Pointing `curl.cainfo` and `openssl.cafile` at a CA bundle fixed it; that is local setup, not a code change.

## Backlog follow-ups (applied at /ship)

- **T-05:** stays `enriched`, with `change: spotify-api-client` once that change is proposed. Its client scenarios (refresh, 401, timeout, 429, typed errors, `APP_KEY` rotation) move there.
- **T-04:** add the `@manual` check "Spotify accepts the production redirect" (T-04 follow-up in T-05).
- **T-08:** the connection identifier is `spotify_connections.connection_id`, through `SpotifyConnectionRepository::current()`.
- **T-06 and T-14:** the shared props are `spotify.status` and `spotify.connected`.
- **T-06:** `GET /spotify/token` is the endpoint that "Spotify tokens stay off the pages" allows to send the access token to the browser player. The refresh token never leaves the server, and no page or shared prop may carry either token.
- **T-18 and T-25:** `/settings` redirects to `/settings/spotify`. The page has a "Back" link and the user menu a "Settings" item, until the settings sub-navigation and the Aero navigation exist.
