# Design

## Context

See proposal.md (Why). This change follows `spotify-api-client` (archived 2026-11-14), the second half of T-05. Its client, `App\Spotify\SpotifyClient`, solved the same problems for Spotify:

- a refresh under a cache lock, with a re-read inside the lock;
- typed failures in the application layer;
- marking the connection for reconnecting by `connection_id`;
- no logs, no `->throw()`, `#[\SensitiveParameter]` on every token argument;
- tests with a Mockery proxy around the cache manager and `Sleep::fake(syncWithCarbon: true)`.

Current state after `youtube-music-connection`:

- **Data:** `youtube_music_connections` has the encrypted tokens, `access_token_expires_at`, `status` (`connected` or `reconnect_required`) and `connection_id`. `YouTubeMusicConnectionRepository` has `current()`, `status()`, `storeForOwner()` and `deleteForOwner()`.
- **Google:** `App\YouTubeMusic\GoogleDeviceFlow` holds the client configuration and the device-flow requests.
- **Settings page:** it already shows `reconnect_required`, and it shows "YouTube Music is not configured correctly." only as a flashed message after a refused device code.
- **Google's behaviour:**
  - access tokens last about an hour;
  - a refresh answer usually carries no new refresh token;
  - while the app is in "Testing", the refresh token stops working after 7 days with `invalid_grant` (T-27).

## Goals / Non-Goals

**Goals**

- One service that T-12 and later features inject to get a token, so none of them copies the renewal.
- The 7-day expiry ends in a clear "reconnect" state, not in repeated errors.

**Non-Goals**

- A generic YouTube API client with retries on 401. T-12 decides how it calls the Data API. If it needs a retry, it can ask the service for a forced renewal then.
- A shared pause after a 429 (proposal Non-goals).

## Decisions

### D1. `App\YouTubeMusic\YouTubeMusicCredentials::accessToken(): string`

It is infrastructure next to `GoogleDeviceFlow`, injected by handlers only. The steps:

1. **Connection:**
   - no row: throw `YouTubeMusicNotConnected`;
   - status `reconnect_required`: throw `YouTubeMusicReconnectRequired`;
   - missing tokens, or a `DecryptException` while reading them: mark the connection for reconnecting and throw `YouTubeMusicReconnectRequired` (D4).
2. **Fresh:** a token whose expiry is 60 seconds or more away is returned as it is. A missing expiry counts as expired.
3. **Configured:** if `GoogleDeviceFlow::isConfigured()` is false, set the configuration-error mark and throw `YouTubeMusicMisconfigured`, without calling Google.
4. **Renew:** run the renewal under the lock (D2), and return the new token.

The architecture test adds `App\YouTubeMusic\YouTubeMusicCredentials` to the classes that entry points must not use.

*Alternative rejected:* returning a value object with the remaining lifetime, like `SpotifyAccessToken`. That was for the browser player's SDK; YouTube playback does not use the owner's token (T-27). A string is enough for server-side calls.

### D2. Renewal under a cache lock, with a re-read

The renewal runs inside `Cache::lock('youtube-music:token-refresh', 15)->block(10, …)`:

- **Re-read:** inside the lock, the service reads the connection again before renewing. In none of these cases does it send anything to Google:

  | Found inside the lock | Result |
  |------|------|
  | A token valid for 60 seconds or more, stored by another request | that token is returned; this covers "Parallel requests renew only once" |
  | No connection: it was disconnected meanwhile | `YouTubeMusicNotConnected` |
  | Another `connection_id`: it was replaced by a reconnect | the new connection is handled as at step 1 of D1 and renewed if needed |
  | The status `reconnect_required`: another request marked it | `YouTubeMusicReconnectRequired` |

  This follows `SpotifyClient::refreshLocked()`.
- **Lock timeout:** a `LockTimeoutException` throws `YouTubeMusicUnavailable`.
- **Request:** `GoogleDeviceFlow::requestRefresh(#[\SensitiveParameter] string $refreshToken): Response` sends `POST https://oauth2.googleapis.com/token`, as a form, with `client_id`, `client_secret`, `refresh_token` and `grant_type=refresh_token`, and a 10 s timeout. It never calls `->throw()` and lets a `ConnectionException` bubble.

The answer is interpreted like this:

| Answer | Result |
|------|------|
| 2xx with an `access_token` | `storeRefreshedTokens()`, which also clears the configuration-error mark; return the token |
| 400 with `error=invalid_grant` | mark the connection for reconnecting; `YouTubeMusicReconnectRequired` |
| `error=invalid_client` (Google answers it with 401) | set the configuration-error mark; `YouTubeMusicMisconfigured` |
| 5xx, 429, `ConnectionException` | `YouTubeMusicUnavailable`, nothing stored |
| anything else, including a 2xx without a token | `YouTubeMusicUnavailable` |

The tests use the same techniques as `SpotifyClientTest`:

- **The re-read:** wrap the real cache manager in a Mockery proxy, `Cache::swap(Mockery::mock(app('cache')))`, so the lock can be faked while the cache still works. The fake lock's `block()` first stores "the other request's" fresh token, then runs the callback.
- **A lock that never frees:** take the lock first and use `Sleep::fake(syncWithCarbon: true)`, so the 10 s wait passes instantly.

*Alternative rejected:* sharing `ConnectionAttempts::LOCK_KEY`. A renewal and a device-flow poll do not conflict, and one lock per concern keeps the waits short.

### D3. Four typed failures

They live in `app/Application/YouTubeMusic/Exceptions` and extend the abstract `YouTubeMusicCredentialsFailed`, which extends `UseCaseFailed`:

- `YouTubeMusicNotConnected`;
- `YouTubeMusicReconnectRequired`;
- `YouTubeMusicUnavailable`;
- `YouTubeMusicMisconfigured`.

Their messages are fixed texts. Handlers let them bubble, and entry points (T-12) map them to their own message or status. This mirrors `SpotifyApiFailed` and its subclasses. The existing `YouTubeMusicNotConfigured` stays as it is for the connect flow: it is thrown before any connection exists and is not a credentials failure.

### D4. Repository writes by `connection_id`, and the configuration-error mark

- **Migration:** it adds `config_error_at` (nullable timestamp) to `youtube_music_connections`. `storeForOwner()` resets it to null, so reconnecting clears it.
- **New repository methods:** all of them update only the row with that `connection_id`, so a connection replaced meanwhile is left alone:

  | Method | What it writes |
  |------|------|
  | `storeRefreshedTokens(string $connectionId, #[\SensitiveParameter] string $accessToken, #[\SensitiveParameter] ?string $refreshToken, int $expiresIn)` | the new tokens and expiry, saving the model so the `encrypted` casts apply; keeps the refresh token when the new one is null; sets `config_error_at` to null |
  | `markReconnectRequired(string $connectionId)` | status `reconnect_required`; nulls both tokens and the expiry |
  | `markConfigError(string $connectionId)` | `config_error_at = now()`, unless it is already set |

### D5. The settings page reads the mark

`YouTubeMusicSettings` gains `misconfigured: bool`. It is true when the client is configured, the status is `connected` and `config_error_at` is set.

When the client is not configured at all, the page already shows "YouTube Music is not configured.", so the second text is not shown too.

`settings/YouTubeMusic.vue` shows "YouTube Music is not configured correctly." under "YouTube Music connected" and the channel name, in the error tone, with `data-part="config-error"`.

### D6. No secrets out

- no `Log::` calls and no `->throw()`;
- fixed exception messages, and no response body in any exception;
- `#[\SensitiveParameter]` on every token argument of `requestRefresh()`, `storeRefreshedTokens()` and the service's private helpers.

The test listens to `MessageLogged` and checks the failure's message too.

## Risks / Trade-offs

- [No feature calls the service yet, so a mistake would only show in T-12] → the feature tests drive the service directly, and the manual check renews a real token.
- [A 401 from the Data API with a token that looks fresh, for example after a revocation, is not handled here] → T-12 can map it to "reconnect required" or ask for a renewal. The service's next renewal returns `invalid_grant` anyway, which marks the connection.
- [The `database` cache shares the lock between PHP processes; an `array` cache would not] → the README already requires a shared cache store.
- [The mark set while the client was not configured stays after the `.env` is fixed, as long as the stored token is still valid, so up to an hour] → the next renewal clears it, and so does reconnecting. The page hides the mark while the client is not configured, so the owner sees "YouTube Music is not configured." first.

## Migration Plan

1. Deploy, then run `php artisan migrate`, which adds `config_error_at`.
2. Nothing else changes: existing connections have no mark.

**Rollback:** roll back the migration, which drops the column.

## Manual checks

**2026-11-15, local app with the owner's real connection (task 4.3):**

- The stored access token's expiry was set one minute in the past, and `YouTubeMusicCredentials::accessToken()` was called through `php artisan tinker --execute`, without printing the token.
- Google renewed it: the service returned a new token, the same one now stored. The expiry moved one hour ahead, and the connection kept the status `connected` without a configuration error.
- Nothing from the account went into the repository or the chat.
