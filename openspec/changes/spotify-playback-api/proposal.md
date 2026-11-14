# Proposal

## Why

T-06 (play Spotify in the browser) is split in two. This first change adds the two server endpoints the browser player needs: one that hands the Web Playback SDK a fresh access token, and one that starts an album, playlist or tracks on the browser's device. The second change, `spotify-web-player`, builds the SDK, the persistent layout, the bar and the controls on top of them. Doing the API first keeps each change small and lets the frontend rely on endpoints that are already tested.

## What Changes

- `GET /spotify/token` answers `{access_token, expires_in}` with `Cache-Control: no-store`, through the shared client, which refreshes the token when needed. It never returns the refresh token.
- `PUT /spotify/player/play` takes `{device_id, context_uri?, uris?, offset?}` with exactly one of `context_uri` or `uris`, and starts playback on that device through the Web API. `offset` defaults to `{position: 0}`.
- Both endpoints always answer JSON, never an HTML error page:
  - 401 `unauthenticated` for a JSON request without a session;
  - 409 `spotify_not_connected` and 409 `spotify_reconnect_required`;
  - 429 `rate_limited` with `retry_after`, both from Spotify and during the shared pause;
  - 502 `spotify_unavailable`.
- The start endpoint also answers:
  - 419 `session_expired` when the session expired and the CSRF token no longer matches;
  - 422 for a malformed request, without calling Spotify;
  - 403 `premium_required`;
  - 404 `device_not_found`;
  - 422 `playback_rejected` for any other refusal from Spotify.
- `SpotifyClient::accessToken()` returns the token with its remaining lifetime, so the token endpoint can report `expires_in`.

## Non-goals

- The SDK, the device, the bar, the controls, the persistent layout and the remembered volume (`spotify-web-player`).
- What happens when playback moves to another device, and the SDK's own errors (T-23).
- The queue, shuffle and repeat (T-13), and search and library screens (T-07, T-08).

## Capabilities

### New Capabilities

- `spotify-playback`: playing the owner's Spotify music in the browser. This change adds the token endpoint and the start endpoint; `spotify-web-player` adds the player itself.

### Modified Capabilities

None.

## Impact

- **New:**
  - two invokable controllers in `app/Http/Controllers/Spotify`;
  - the query `GetSpotifyPlaybackToken` and the command `StartSpotifyPlayback`, with their result and failures in `app/Application/Spotify`;
  - a form request for the start endpoint;
  - a trait that maps Spotify failures to JSON errors, which T-23 reuses.
- **Changed:**
  - `SpotifyClient::accessToken()` returns a value object instead of a string;
  - `bootstrap/app.php` answers a JSON 401 for these endpoints;
  - `routes/web.php` and `tests/Feature/RoutesTest.php` get two routes.
- **Tests:** feature tests with `Http::fake()`. No test reaches Spotify.
- **Backlog:** T-06 links to this change and stays open for `spotify-web-player`.
