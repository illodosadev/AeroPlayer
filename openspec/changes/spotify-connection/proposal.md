# Proposal

Implements backlog task **T-05** (conectar la cuenta de Spotify), part 1 of 2. The shared Web API client follows in `spotify-api-client`, which closes T-05.

## Why

Every Spotify feature (T-06 playback, T-07 search, T-08 library, T-15, T-23, T-24) needs the owner's Spotify account connected, with its tokens kept on the server. The discovery asks for these tokens to be stored securely, and T-05 limits the connection to the owner's own Premium account.

## What Changes

- **Connect:** `GET /spotify/connect` sends the owner to Spotify's authorization page (Authorization Code flow with the client secret). It asks for every scope the v1 tasks need, with a single-use random state and `show_dialog=true`.
- **Callback:** `GET /spotify/callback` checks the state, exchanges the code and reads `GET /me`. It stores the connection only for the owner account (`SPOTIFY_OWNER_ID`), rejects a non-`premium` `product`, and answers every failure with an English message.
- **Storage:** at most one connection, with the tokens encrypted at rest, the account, the granted scopes, the expiry, the authorization date, a status (`connected` or `reconnect_required`) and an identifier that changes on each connection.
- **Settings page `/settings/spotify`:** built with Aero components. It shows the connection state and messages, "Reconnect before <date>" (6 months after authorizing), missing scopes, `reconnect_required` and "not configured". It has "Connect Spotify" and "Disconnect Spotify" (`DELETE /spotify/connection`). `GET /settings` redirects to it, and the user menu gains a "Settings" link.
- **Shared Inertia props:** `spotify.status` (`not_connected`, `connected`, `reconnect_required`) and `spotify.connected`. The refresh token never leaves the server, and no page or shared prop carries a token.
- **Configuration and docs:** `SPOTIFY_CLIENT_ID`, `SPOTIFY_CLIENT_SECRET`, `SPOTIFY_REDIRECT_URI` and `SPOTIFY_OWNER_ID` in `.env.example`, plus a README section on registering the Spotify app.

## Capabilities

### New Capabilities

- `spotify-connection`: connecting, storing and disconnecting the owner's Spotify account, and the Spotify settings page.

### Modified Capabilities

- `owner-auth`: "Registration, password reset, verification, settings and two-factor are removed" no longer lists "/settings" as a 404 route, and the user menu now links to the Spotify settings page.

## Non-goals

- The shared Web API client: refresh, 401 retry, timeout, 429 block and typed errors (change `spotify-api-client`). That change also detects revoked access and sets `reconnect_required`; this change only shows that state.
- Playback, search, library and Spotify Connect (T-06, T-07, T-08, T-15, T-23, T-24).
- The settings sub-navigation (T-18 or T-09) and the Aero app navigation (T-25).
- Revoking access on Spotify's side: Spotify has no revocation endpoint, so the page points to spotify.com/account/apps.
- Checking the production redirect URI: it waits for the deployment (T-04).

## Impact

- **Backend:**
  - `spotify_connections` table, `SpotifyConnection` model and `SpotifyConnectionRepository`;
  - an OAuth service on Laravel's HTTP client;
  - two controllers and new routes in `routes/web.php`;
  - `config/services.php`;
  - the shared props in `HandleInertiaRequests`.
- **Frontend:** `resources/js/pages/settings/Spotify.vue` (Aero), the "Settings" item in `UserMenuContent.vue`, and shared prop types.
- **Tests:** feature tests with `Http::fake()`, a browser test for the page, and the `owner-auth` tests updated.
- **Docs:** `.env.example` and the README.
- **Dependencies:** none new.
