# Design

## Context

See proposal.md, "Why". The requirements are in `specs/spotify-web-api/spec.md`. Backlog task T-05 holds the research: token lifetimes, `invalid_grant`, rate limits with a rolling 30-second window and `Retry-After`.

What already exists:

- **The connection:**
  - `spotify_connections` stores encrypted `access_token` and `refresh_token`, `access_token_expires_at`, `status` (`connected` or `reconnect_required`) and `connection_id`;
  - `SpotifyConnectionRepository` has `current()`, `status()`, `storeForOwner()` and `deleteForOwner()`;
  - the settings page already asks to reconnect when the status is `reconnect_required`, and never decrypts the tokens.
- **`SpotifyOAuth`:** sends the code exchange with HTTP Basic client credentials and a timeout of `config('services.spotify.timeout')` (10 s). It never calls `->throw()`, because Laravel's `RequestException` embeds part of the response body.
- **The rules:**
  - CQRS: handlers are the only callers of repositories and infrastructure, and failures extend `App\Application\UseCaseFailed`;
  - single-action controllers.
- **The cache:** `database` locally and in production, `array` in the tests. Both support atomic locks.

## Goals / Non-Goals

**Goals:**

- One client every Spotify feature uses, so the refresh, the 401 retry, the shared pause and the failure types live in one place.
- No Spotify problem reaches the user as a 500, and no secret reaches a log or an exception.

**Non-Goals:**

- Commands, queries or routes that use the client. The first one comes with T-06.
- Retrying after a 429 or 5xx, or queueing calls during the pause.

## Decisions

### D1. `App\Spotify\SpotifyClient`, infrastructure used by handlers

```php
$client->get('/me/playlists', ['limit' => 50]);   // Illuminate\Http\Client\Response
$client->send('PUT', '/me/player/play', ['json' => [...]]);
```

- `accessToken(): string` runs steps 1–3 of D3 and returns a fresh access token. It is for the Web Playback SDK token endpoint of T-06, so that endpoint reuses the refresh and its lock instead of copying them.
- Paths are relative to `https://api.spotify.com/v1`.
- `get()` is a shortcut for `send('GET', …)`. `send()` takes the method, the path and Laravel HTTP options (`query` or `json`).
- It returns the `Response` for anything that is not a 401, a 429 or a 5xx (spec "Other Web API answers reach the caller").
- It lives next to `SpotifyOAuth` in `app/Spotify`, so it is infrastructure in the CQRS sense: query and command handlers inject it. The architecture test adds `App\Spotify\SpotifyClient` to what entry points must not use.

*Alternatives:*

- Typed methods per endpoint (`playlists()`, `search()`…). Rejected: each feature task knows its endpoints, and a generic `send()` keeps this change about the transport.
- A Guzzle middleware stack. Rejected: Laravel's HTTP client with a few explicit steps is easier to read and to fake.

### D2. Four typed failures in the application layer

All four live in `app/Application/Spotify/Exceptions` and extend the abstract `SpotifyApiFailed`, which extends `UseCaseFailed`:

- `SpotifyNotConnected`;
- `SpotifyReconnectRequired`;
- `SpotifyUnavailable`;
- `SpotifyRateLimited`, with `public readonly int $retryAfter` in seconds.

Handlers let them bubble. Entry points catch `SpotifyApiFailed`, or one subclass, and map it to their own message or status. Messages are fixed texts.

*Alternatives:*

- One exception with a reason enum, like `SpotifyConnectionFailed(ConnectFailure)`. Rejected: T-05 names four typed errors, and callers often treat one of them differently, for example the 409 `spotify_reconnect_required`. Separate classes let `catch` express that.
- Exceptions in `app/Spotify`. Rejected: the CQRS rule makes use-case failures `UseCaseFailed` exceptions that entry points map. That makes the infrastructure client depend on application exceptions, which is accepted here because the client exists only to serve handlers.

### D3. The call, step by step

1. **Pause:** if the shared pause is active, throw `SpotifyRateLimited` with the remaining seconds. Nothing is sent (D5).
2. **Connection:** `current()`. With no row, throw `SpotifyNotConnected`. With status `reconnect_required` or a null token, throw `SpotifyReconnectRequired`. Reading the tokens can throw `DecryptException`: then mark the connection for reconnecting and throw `SpotifyReconnectRequired` (D6).
3. **Fresh token:** if `access_token_expires_at` is less than 60 seconds away, or empty, refresh (D4). An empty expiry should never happen with stored tokens, so it counts as expired.
4. **Send:** `Http::withToken($access)->acceptJson()->timeout(config('services.spotify.timeout'))`, which is 10 s, to the URL. A `ConnectionException` throws `SpotifyUnavailable`.
5. **Answer:**
   - **401:** force a refresh (D4) and repeat once. A second 401 marks the connection for reconnecting and throws `SpotifyReconnectRequired`.
   - **429:** start the pause and throw `SpotifyRateLimited`.
   - **5xx:** throw `SpotifyUnavailable`.
   - **Anything else:** return the response.

### D4. Refresh under a cache lock

- `SpotifyOAuth::requestRefresh(string $refreshToken): Response` sends `grant_type=refresh_token` with the client credentials, never calls `->throw()`, and lets a `ConnectionException` bubble.
- The client runs the refresh inside `Cache::lock('spotify:token-refresh', 15)->block(10, …)`.
- Inside the lock it reads the connection again before refreshing:
  - **before a call:** if another call already stored a token valid for 60 seconds or more, it uses that token and sends nothing (spec "Parallel calls refresh the token only once");
  - **after a 401:** if the stored access token is no longer the one that got the 401, another call already refreshed it, so it uses the stored token. If it is still the same token, it refreshes.
- The client takes the lock through `Cache::lock()`. A test can therefore swap it, by wrapping the real cache manager in a Mockery proxy (`Cache::swap(Mockery::mock(app('cache')))`; `Cache::partialMock()` builds a manager without its application and breaks the pause), for a fake lock whose `block()` first performs "the other call's" write and then runs the callback. That is the only way to exercise the re-read in a single process.
- `Lock::block()` measures its timeout with `now()` and waits with `Sleep::usleep()`. With a frozen clock it would never time out, so a test of a lock that never frees uses `Sleep::fake(syncWithCarbon: true)`: each fake sleep moves Carbon forward, and the real 10 s timeout passes instantly.
- It interprets the answer like the Web API:
  - **429:** start the pause;
  - **5xx or `ConnectionException`:** `SpotifyUnavailable`;
  - **400 with `error=invalid_grant`:** mark the connection for reconnecting and throw `SpotifyReconnectRequired`;
  - **any other non-2xx, for example `invalid_client` after a secret change:** `SpotifyUnavailable`, because reconnecting would not fix a server configuration problem;
  - **2xx:** `storeRefreshedTokens()`, keeping the old refresh token when the answer has none.
- Waiting longer than 10 seconds for the lock throws `LockTimeoutException`, which the client maps to `SpotifyUnavailable`.

*Alternative:* a database row lock (`lockForUpdate`). Rejected: T-05 settled on `Cache::lock`, which also works with SQLite in the tests.

### D5. The shared pause

- The client stores the "paused until" Unix timestamp under `spotify:paused-until`, with the pause length as TTL.
- The pause lasts the `Retry-After` value in seconds, or 5 seconds when the header is missing or not a number.
- The remaining wait is `ceil(until - now)`, at least 1.
- It is checked before any request, the refresh included, and is the same key for every feature.
- The tests use time travel. With the `array` store, the TTL and `now()` follow the travelled clock.

### D6. Marking the connection for reconnecting

- `SpotifyConnectionRepository::markReconnectRequired(string $connectionId)` sets the status to `reconnect_required` and nulls both tokens and the expiry, by `connection_id`. A newer connection made meanwhile is therefore left alone.
- `storeRefreshedTokens(string $connectionId, string $accessToken, ?string $refreshToken, int $expiresIn)` updates in the same way.
- Both are write methods called only by the client, which handlers drive. That keeps the rule that handlers, and the infrastructure they inject, are the only route to repositories.
- On a `DecryptException` the client marks the connection for reconnecting without sending anything. `APP_PREVIOUS_KEYS` still lets an owner rotate the key without losing the tokens (README).

### D7. No secrets out

- No `Log::` calls.
- No `->throw()`.
- Exception messages are fixed texts.
- The client never puts a response body into an exception.
- Every parameter that receives a token carries `#[\SensitiveParameter]`: `SpotifyOAuth::requestRefresh()`, `SpotifyConnectionRepository::storeRefreshedTokens()` and the client's private helpers. A stack trace then shows `Object(SensitiveParameterValue)` instead of the token, even where the server has `zend.exception_ignore_args` off.

The spec scenario checks the failure message, and checks that no log message contains a secret, after an `invalid_grant` whose body echoes the refresh token. It does not assert an empty log: PHP deprecation notices may legitimately reach it.

## Risks / Trade-offs

- [The `array` cache in a deployment would make the lock and the pause per-process] → The README states that the cache store must be shared. Production uses `database` (T-04).
- [A long lock wait under heavy parallel use] → The lock waits at most 10 s, then the call fails as `SpotifyUnavailable` instead of hanging. One user rarely sends parallel calls.
- [The `Retry-After` header as an HTTP date] → Spotify sends seconds. A non-numeric value falls back to 5 s.
- [Spotify answers 403 when the owner's Premium lapsed] → The client returns it unchanged. Each feature decides the message (T-06).
- [The infrastructure depends on application exceptions] → Accepted, and limited to these four classes (D2).

## Migration Plan

No schema change: the columns exist since `spotify-connection`. Deploy as usual. Rollback means reverting the commit.

## Backlog follow-ups (applied at /ship)

- **T-05:** `status: done`. Both changes are archived.
- **T-06, T-07, T-08, T-15, T-23 and T-24:**
  - handlers inject `App\Spotify\SpotifyClient` and let `SpotifyApiFailed` bubble;
  - entry points map `SpotifyNotConnected` to 409 `spotify_not_connected`, `SpotifyReconnectRequired` to 409 `spotify_reconnect_required`, and choose their own text for `SpotifyUnavailable` and `SpotifyRateLimited::$retryAfter`;
  - other answers (403, 404) come back as a `Response`.
- **T-06:** the `GetSpotifyPlaybackToken` handler calls `SpotifyClient::accessToken()`, which refreshes under the same lock (D1, D4), and returns the token to `ShowSpotifyPlaybackTokenController`.
