# Proposal

## Why

T-05 was split in two. `spotify-connection` stores the owner's tokens. This second part adds the **shared Spotify Web API client** that T-06, T-07, T-08, T-15, T-23 and T-24 need. It keeps the token fresh, survives revoked access and rate limits, and never turns a Spotify failure into a 500. It closes T-05.

## What Changes

- A Web API client that reads the stored connection and sends the request with a 10 s timeout:
  - it refreshes the access token when it expires in less than 60 seconds, under a cache lock so that parallel requests send only one refresh;
  - it stores a rotated refresh token, and keeps the old one when Spotify sends none;
  - on a 401 it refreshes and retries once.
- **Typed failures**, thrown before or instead of the call:
  - **not connected:** there is no connection;
  - **reconnect required:** the connection is already marked so, or Spotify rejected the refresh (`invalid_grant`), or a second 401 came back, or the tokens no longer decrypt after an `APP_KEY` change. The client then deletes the tokens and marks the connection, so the settings page asks to reconnect;
  - **unavailable:** 5xx, connection error or timeout. The tokens stay untouched;
  - **rate limited:** a 429 from the Web API or the token endpoint starts one pause shared by every feature (the `Retry-After` value, or 5 s if it is missing). During the pause nothing is sent to Spotify, not even a refresh, and the failure carries the remaining wait.
- Any other response (2xx, 403, 404…) goes back to the caller to interpret.
- The client logs nothing, and its failures never carry tokens, codes, secrets or response bodies.
- The CQRS architecture test forbids entry points from using the client: only handlers call it.

## Non-goals

- Endpoints for playback, search or the library, and each screen's messages for these failures (later tasks).
- Retrying automatically after a 429 or a 5xx: the client only reports, and each caller decides.
- Revoking tokens on Spotify, or warning before the refresh token's 6 months end (the settings page shows the date).

## Capabilities

### New Capabilities

- `spotify-web-api`: calling the Spotify Web API on the owner's behalf: the stored connection, token refresh, typed failures, the shared rate-limit pause and secrets kept out of errors.

### Modified Capabilities

None. `spotify-connection` already asks to reconnect for a connection marked `reconnect_required`; this change adds the code that sets it.

## Impact

- **New:**
  - `App\Spotify\SpotifyClient`;
  - the typed failures in `app/Application/Spotify/Exceptions`.
- **Changed:**
  - `SpotifyOAuth` gains the refresh request;
  - `SpotifyConnectionRepository` gains the methods to store refreshed tokens and to mark the connection for reconnecting;
  - `tests/Unit/ArchitectureTest.php` adds the client to what entry points must not use.
- **Tests:** feature tests against `Http::fake()`, with time travel. No test reaches Spotify.
- **Docs:** the README notes that the cache store must be shared between processes.
- **Backlog:** T-05 links to this change and closes with it.
