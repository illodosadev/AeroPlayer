# Design

## Context

See proposal.md, "Why". The requirements are in `specs/spotify-playback/spec.md`, and the research is in T-06 (the Web Playback SDK and `PUT /v1/me/player/play`).

What already exists:

- **`App\Spotify\SpotifyClient`** (`spotify-web-api`):
  - `get()`, `send()` and `accessToken(): string`;
  - the four failures extend `SpotifyApiFailed`: `SpotifyNotConnected`, `SpotifyReconnectRequired`, `SpotifyUnavailable` and `SpotifyRateLimited` with `$retryAfter`;
  - any other answer, 403 and 404 included, comes back as a `Response`.
- **CQRS:** commands and queries with handlers in `app/Application/<Context>`, dispatched through the buses. Failures extend `UseCaseFailed`, and queries never return models.
- **Single-action controllers:** every new route also goes into `tests/Feature/RoutesTest.php`.
- **`bootstrap/app.php`:** has no exception rendering of its own. For a JSON request without a session, Laravel answers 401 `{"message": "Unauthenticated."}`.
- **Spotify's start call:** `PUT /v1/me/player/play?device_id=…` with `{context_uri, offset}` or `{uris, offset}` answers 204. It answers 403 with `reason: PREMIUM_REQUIRED` without Premium, 404 for an unknown device and 400 for a bad URI.

## Goals / Non-Goals

**Goals:**

- Two small JSON endpoints whose error codes T-23 and the browser player can rely on.
- No refresh logic outside `SpotifyClient`.

**Non-Goals:**

- Anything in the browser. That is `spotify-web-player`.
- Mapping SDK errors or the move to another device (T-23).

## Decisions

### D1. Routes and controllers

| Route | Name | Controller | Use case |
|---|---|---|---|
| GET `spotify/token` | `spotify.token` | `Spotify/ShowSpotifyPlaybackTokenController` | query `GetSpotifyPlaybackToken` |
| PUT `spotify/player/play` | `spotify.player.play` | `Spotify/StartSpotifyPlaybackController` | command `StartSpotifyPlayback` |

- Both routes sit in the existing `auth` group in `routes/web.php`, so they also run the `web` middleware: session and CSRF.
- The browser player sends the `X-XSRF-TOKEN` header that Laravel's cookie provides. That is `spotify-web-player`'s concern; Laravel skips CSRF in the tests.

### D2. The token comes from `SpotifyClient::accessToken()`, now with its lifetime

- `accessToken()` returns `App\Spotify\SpotifyAccessToken(string $value, int $expiresIn)`. `expiresIn` is the whole number of seconds left until `access_token_expires_at`, and never negative.
- The only caller today is its own test, which changes with it.
- `GetSpotifyPlaybackTokenHandler` calls it and returns `Results\SpotifyPlaybackToken(accessToken, expiresIn)`. The token parameter carries `#[\SensitiveParameter]`.
- **A query that may refresh:** T-06's CQRS note asked whether the refresh belongs in a separate command. It stays inside the query. A refresh renews a credential the client manages as infrastructure; it does not change any state the owner sees. A separate command would also force two bus calls and a race between them.

*Alternative:* a `RefreshSpotifyToken` command, then the query. Rejected for the race and for the duplication with the client, which already refreshes under its lock.

### D3. The start command and its failures

- `StartSpotifyPlayback(string $deviceId, ?string $contextUri, ?array $uris, array $offset)`.
- The handler sends `PUT /me/player/play` through the client with `['query' => ['device_id' => …], 'json' => [context_uri|uris, offset]]` and reads the answer:
  - **2xx:** done; the command returns nothing.
  - **403 with `error.reason = PREMIUM_REQUIRED`:** `SpotifyPremiumRequired`.
  - **404:** `SpotifyDeviceNotFound`.
  - **any other 4xx:** `SpotifyPlaybackRejected`. A bad URI, or a 403 for another reason, cannot be fixed by retrying.
- The three new failures live in `app/Application/Spotify/Exceptions` and extend `SpotifyApiFailed`, so one `catch` covers every Spotify failure. The client's four failures bubble unchanged.

*Alternative:* return the `Response` to the controller and map there. Rejected: entry points must not interpret Web API answers (CQRS), and T-23 needs the same failures from its own handlers.

### D4. Validation

`StartSpotifyPlaybackRequest` checks:

- `device_id`: required, string;
- `context_uri`: string, `required_without:uris`, `prohibits:uris`;
- `uris`: an array of strings, `required_without:context_uri`;
- `offset`: optional, either `position` as an integer ≥ 0 or `uri` as a string. When it is missing, the default is `{position: 0}`. The `offset.*` rules apply only when `offset` is present (`sometimes`); otherwise a request without `offset` would fail on `offset.position` being required.

The URIs are not checked against Spotify's format: Spotify answers 400 for a bad one, which becomes `playback_rejected`. A JSON request that fails validation gets Laravel's 422 JSON, and nothing is sent to Spotify.

### D5. One JSON error mapping, shared

The trait `Spotify\Concerns\RespondsWithSpotifyErrors` has `spotifyError(SpotifyApiFailed $failure): JsonResponse`:

| Failure | Status | `error` |
|---|---|---|
| `SpotifyNotConnected` | 409 | `spotify_not_connected` |
| `SpotifyReconnectRequired` | 409 | `spotify_reconnect_required` |
| `SpotifyRateLimited` | 429 | `rate_limited`, plus `retry_after` |
| `SpotifyUnavailable` | 502 | `spotify_unavailable` |
| `SpotifyPremiumRequired` | 403 | `premium_required` |
| `SpotifyDeviceNotFound` | 404 | `device_not_found` |
| `SpotifyPlaybackRejected` | 422 | `playback_rejected` |

Both controllers catch `SpotifyApiFailed` and return this response, and T-23's controllers reuse the trait. Every response, success or error, carries `Cache-Control: no-store`.

### D6. JSON answers for a missing or expired session

`bootstrap/app.php` renders two exceptions as JSON when the request expects JSON:

- **`AuthenticationException`**, on `spotify/token` or `spotify/player/*`: `{"error": "unauthenticated"}` with status 401.
- **`TokenMismatchException`**, on `spotify/player/*`: `{"error": "session_expired"}` with status 419. In the `web` group the CSRF check runs before `auth`, so a tab whose session expired gets this answer, not the 401. GET `spotify/token` skips the CSRF check. Laravel's handler turns the `TokenMismatchException` into an `HttpException(419)` before the render callbacks run (`prepareException`), so the callback is typed on `HttpException` and checks the status and `getPrevious()`.

Every other route keeps Laravel's behavior: a redirect to `/login`, the 419 page, or `{"message": …}`.

Laravel skips the CSRF check while running tests, so the 419 test calls the exception handler directly, with a JSON request to `spotify/player/play` and a `TokenMismatchException`.

*Alternative:* answer 401 from the controllers. Rejected: the `auth` and CSRF middleware stop the request before any controller runs.

## Risks / Trade-offs

- [The token reaches the browser] → That is the purpose of the endpoint (spotify-connection, "Spotify tokens stay off the pages", allows it). It is the access token only: never the refresh token, with `no-store`, behind the session, and the token lasts at most an hour.
- [Changing `accessToken()`'s return type] → It has no caller outside its test yet. The value object keeps the token and its lifetime together.
- [`playback_rejected` hides Spotify's exact reason] → The JSON keeps the code short. T-23 can refine it if a screen needs more.

## Migration Plan

No schema change. Deploy as usual. Rollback means reverting the commit.

## Backlog follow-ups (applied at /ship)

- **T-06:** stays `enriched`, with `change: spotify-web-player` once that change is proposed.
- **T-06 (`spotify-web-player`):**
  - the browser player calls `GET /spotify/token` from the SDK's `getOAuthToken`;
  - `play()` calls `PUT /spotify/player/play` with the `X-XSRF-TOKEN` header;
  - every request sends `Accept: application/json`, or Laravel redirects instead of answering 401 or 422;
  - it handles the JSON error codes of D5, and sends the owner to sign in on a 401 or a 419 `session_expired`.
- **T-23:** its endpoints reuse `RespondsWithSpotifyErrors` and the failures `SpotifyPremiumRequired`, `SpotifyDeviceNotFound` and `SpotifyPlaybackRejected`.
