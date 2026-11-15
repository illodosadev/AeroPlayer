# Proposal

Implements backlog task **T-09** (conectar YouTube Music), part 1 of 2. The shared credentials service follows in `youtube-music-credentials`, which closes T-09.

## Why

The YouTube Music library (T-12) reads the owner's playlists and Liked Music through the official YouTube Data API, which needs the owner's Google authorization. The T-27 spike settled how: Google's OAuth device flow, an OAuth client of the owner's own and the read-only scope `youtube.readonly`, so decision D6 (no write permissions) holds.

## What Changes

- **Connect:** `POST /youtube-music/connect` asks Google for a device code and shows the owner the user code and Google's verification address. The device code stays on the server.
- **Wait for approval:** the page polls `GET /youtube-music/connect/status` (JSON: `pending`, `connected`, `denied`, `expired`, `not_owner`, `failed`, or `idle` when no attempt is waiting).
  - The server calls Google's token endpoint only once the interval has passed. `slow_down` and a 429 add 5 seconds.
  - Calls run under a lock per attempt.
  - A new attempt replaces the previous one.
- **Owner only:** on approval, the server reads the account's channel and keeps the connection only when it matches `YOUTUBE_MUSIC_OWNER_CHANNEL_ID`. Otherwise it revokes the tokens it received and stores nothing.
- **Storage:** at most one connection: encrypted tokens, channel id and name, scopes, expiry, status (`connected` or `reconnect_required`) and an identifier that changes on each connection.
- **Settings page `/settings/youtube-music`:** Aero, with the states not configured, not connected, waiting, connected and reconnect required. "Disconnect YouTube Music" tries to revoke the token at Google, then deletes the connection.
- **Settings link bar:** the Spotify and YouTube Music settings pages link to each other. `GET /settings` still redirects to `/settings/spotify`.
- **Shared Inertia prop:** `youtubeMusic.status` and `youtubeMusic.connected`, never a token.
- **Configuration:** `YOUTUBE_MUSIC_CLIENT_ID`, `YOUTUBE_MUSIC_CLIENT_SECRET` and `YOUTUBE_MUSIC_OWNER_CHANNEL_ID` in `.env.example`, and a README section on creating the Google OAuth client.

## Capabilities

### New Capabilities

- `youtube-music-connection`: connecting the owner's YouTube Music account with the device flow, storing it, disconnecting it, and the YouTube Music settings page.

### Modified Capabilities

- `spotify-connection`: the Spotify settings page gains the settings link bar that leads to the YouTube Music settings.

## Non-goals

- The credentials service: renewing the access token under a lock, the typed errors, and setting `reconnect_required` or a configuration error when Google refuses a renewal (change `youtube-music-credentials`). This change only shows the `reconnect_required` state.
- Reading the library, searching and playing (T-12, T-11, T-10).
- Verifying the Google app so the authorization stops expiring after 7 days (separate task, to be created).
- Several YouTube accounts, or any account other than the owner's.

## Impact

- **Backend:** a `youtube_music_connections` table with its model and repository, a device-flow service, a `YouTubeMusic` context with four controllers, routes, the status endpoint's JSON 401, `config/services.php` and the shared prop.
- **Frontend:** `settings/YouTubeMusic.vue` (Aero, with polling), a settings link bar shared with `settings/Spotify.vue`, and prop types.
- **Tests:** feature tests with `Http::fake()` and time travel, and browser tests.
- **Docs:** `.env.example` and the README. No new dependencies.
