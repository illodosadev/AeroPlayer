# Design

## Context

See proposal.md (Why). Backlog task T-09 holds the decisions taken with the owner, and T-27 holds the spike results this design relies on:

- the "TV and Limited Input devices" client and the device flow work from PHP;
- Google returns a refresh token for `youtube.readonly`;
- `channels?part=snippet&mine=true` identifies the account;
- the Google app stays in "Testing" for now, so an authorization lasts 7 days.

Current state:

- **Model to follow:** the Spotify connection (`spotify-connection`, archived changes `spotify-connection` and `spotify-api-client`):
  - `spotify_connections` table, `SpotifyConnection` model with `encrypted` and `$hidden` tokens;
  - `SpotifyConnectionRepository`, the `SpotifyConnectionStatus` enum;
  - `App\Spotify\SpotifyOAuth`, which never calls `->throw()` and maps failures to the `ConnectFailure` enum;
  - flash messages on `settings/Spotify.vue`;
  - the `spotify` shared prop.
- **House rules:**
  - CQRS through the buses: a command handler returns nothing and fails with a `UseCaseFailed` exception, and a query never returns a model;
  - single-action controllers, repositories injected only into handlers;
  - `tests/Unit/ArchitectureTest.php` enforces them.
- **Infrastructure:**
  - shared hosting, with no workers or long-running processes;
  - cache and sessions use the `database` driver, which supports locks through `cache_locks`;
  - the test suite never reaches the network.

## Goals / Non-Goals

**Goals**

- The second change (`youtube-music-credentials`) can build its token renewal on this table and repository. If it needs any schema change, the only one is its configuration-error mark.
- The browser's polling rate cannot change how often the server calls Google, so the pacing can be tested without a browser.
- No Google failure becomes a 500, and no secret reaches the logs or the browser.

**Non-Goals**

- Renewing tokens, or calling YouTube after the connection (`youtube-music-credentials`, T-12).
- A "Cancel" button while waiting: the attempt expires by itself, and starting again replaces it.

## Decisions

### D1. Laravel's HTTP client, no Google SDK

`App\YouTubeMusic\GoogleDeviceFlow` makes every call with `Http::asForm()` and a 10 s timeout (`config('services.youtube_music.timeout')`):

| Call | Request |
|------|---------|
| Device code | `POST https://oauth2.googleapis.com/device/code` with `client_id` and `scope=https://www.googleapis.com/auth/youtube.readonly` |
| Token poll | `POST https://oauth2.googleapis.com/token` with `client_id`, `client_secret`, `device_code` and `grant_type=urn:ietf:params:oauth:grant-type:device_code` |
| Channel | `GET https://www.googleapis.com/youtube/v3/channels?part=snippet&mine=true` with the bearer token |
| Revocation | `POST https://oauth2.googleapis.com/revoke` with `token=<refresh token, or access token when there is none>` |

Like `SpotifyOAuth`, it never calls `->throw()`, because Laravel's `RequestException` puts part of the body in its message. Each answer is mapped to a value object or to a `YouTubeMusicConnectFailure` case (D3). It catches `ConnectionException`, and secret arguments carry `#[\SensitiveParameter]`.

*Alternative rejected:* `google/apiclient`.

- It is a large dependency for four requests.
- It does not pace the device flow.
- Its exceptions carry Google's response bodies.

### D2. The waiting attempt lives in the cache, one key for the single owner

Starting the connection stores one entry under `youtube-music:attempt`, with a TTL of `expires_in + 300` seconds. The extra 5 minutes keep the entry readable after `expires_at`, so the next status request can answer `expired`, not `idle`:

```
{ id, device_code (encrypted with Crypt), user_code, verification_url,
  interval, expires_at, next_poll_at }
```

- **One attempt at a time:** a new attempt overwrites the entry, so the previous device code is gone. No request can use it again, which covers "A new attempt replaces the previous one".
- **Encrypted device code:** the cache uses the `database` driver, and the device code must not sit in plain text in the `cache` table or appear in a dump.
- **What the page reads:** the settings page reads only `user_code` and `verification_url`, never the device code.

*Alternatives rejected:*

- **The session:** concurrent requests write the session on a last-write-wins basis, so two polls could undo each other's changes. A per-attempt lock needs the cache anyway.
- **A database table for attempts:** it holds no long-term data, and it would need cleaning up.

### D3. Polling is a command plus a query; failures are a typed exception

`GET /youtube-music/connect/status` changes state: it may call Google, store a connection and end an attempt. It also answers a status. To stay inside CQRS, the controller does two things:

1. It dispatches `CheckYouTubeMusicApproval`. The handler checks, in this order:
   - no attempt: it does nothing;
   - `now >= expires_at`: it removes the entry and fails with `Expired`, without calling Google. This check comes before the pacing and the lock, so an expired code never reaches the token endpoint;
   - `now < next_poll_at`: it does nothing;
   - otherwise it takes the lock (D4), polls Google, and then updates `next_poll_at`, stores the connection or ends the attempt.

   When an attempt ends without a connection, it removes the entry and throws `YouTubeMusicConnectionFailed` with the `YouTubeMusicConnectFailure` case.
2. On success it asks `GetYouTubeMusicConnectStatus`, which answers `pending` (an attempt is waiting), `connected` or `idle`.

On `YouTubeMusicConnectionFailed`, the controller answers `{status}` from the case and flashes the case's message. The page then reloads and shows it. `StartYouTubeMusicConnection` raises the same exception for device code failures, and `StartYouTubeMusicConnectionController` flashes its message.

| `YouTubeMusicConnectFailure` case | Status | Message |
|------|------|------|
| `Unreachable` (device code: 5xx, 429, timeout) | — | "Couldn't reach Google. Try again in a moment." |
| `Misconfigured` (`invalid_client`) | `failed` | "YouTube Music is not configured correctly." |
| `Denied` (`access_denied`) | `denied` | "You denied access to YouTube Music." |
| `Expired` (`expired_token`, or `expires_at` passed) | `expired` | "The code expired. Start again." |
| `NotOwner` (other channel, or no channel) | `not_owner` | "This YouTube account isn't yours. Nothing was connected." |
| `ChannelUnverifiable` (channel: error or timeout) | `failed` | "Couldn't check your YouTube account. Try again." |
| `Rejected` (any other token refusal) | `failed` | "Couldn't connect YouTube Music. Try again." |

While waiting, poll answers that are not final keep the attempt alive:

- `authorization_pending`, a 5xx or a timeout leave `next_poll_at = now + interval`;
- `slow_down` or a 429 add 5 to `interval` first.

*Alternative rejected:* one command that returns the status. Command handlers return nothing, and `ArchitectureTest` checks that.

### D4. A lock per attempt serialises the polls

The handler polls inside `Cache::lock('youtube-music:attempt-poll', 40)->block(35, …)`. The worst case under the lock is the token call, the channel call and a revocation, about 30 s. Inside the lock it reads the entry again:

- **Gone or replaced:** if the entry is gone, or its `id` differs, the handler does nothing, and the query answers from the connection.
- **Paced:** if `now < next_poll_at`, it does nothing.

So two status requests at once send one token request. The second one then finds no attempt and answers `connected`. A lock timeout counts as `pending`, without an error.

*Alternative rejected:* an atomic `Cache::add` flag without waiting. The second request would answer `pending` for a code that is already approved, and the page would need one more poll.

### D5. Data model

The `youtube_music_connections` table follows `spotify_connections`:

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | |
| `user_id` | foreign key to `users` | unique, cascade on delete |
| `connection_id` | uuid | new on every successful connection |
| `channel_id` | string | the owner check |
| `channel_title` | string, nullable | shown on the page |
| `scopes` | text | as Google returns them |
| `access_token`, `refresh_token` | text, nullable | `encrypted` cast, `$hidden` |
| `access_token_expires_at` | timestamp, nullable | `now + expires_in` |
| `authorized_at` | timestamp | |
| `status` | string | `YouTubeMusicConnectionStatus`: `connected` or `reconnect_required` |

`YouTubeMusicConnectionRepository` provides:

- `current()` and `status()`;
- `storeForOwner()`: `updateOrCreate` on `user_id` with a new `connection_id`, which covers reconnecting in place;
- `deleteForOwner()`.

A failed attempt never reaches the repository, so the existing connection stays untouched.

### D6. Approval: owner check, then store or revoke

When the token poll succeeds, the handler reads the channel:

- **Same channel as `services.youtube_music.owner_channel_id`:** store the connection and remove the attempt.
- **Other channel, or no channel:** revoke, remove the attempt, `NotOwner`.
- **Error or timeout on the channel request:** revoke, remove the attempt, `ChannelUnverifiable`.

The revocation result is ignored. Disconnecting (`DisconnectYouTubeMusic`) revokes the stored refresh token the same way, then deletes the row whether or not Google answered. When there is no connection, it sends nothing.

### D7. Routes, controllers and the JSON 401

| Route | Controller | Name |
|------|------|------|
| `GET /settings/youtube-music` | `YouTubeMusic\ShowYouTubeMusicSettingsController` | `youtube-music.settings` |
| `POST /youtube-music/connect` | `YouTubeMusic\StartYouTubeMusicConnectionController` | `youtube-music.connect` |
| `GET /youtube-music/connect/status` | `YouTubeMusic\ShowYouTubeMusicConnectStatusController` | `youtube-music.connect.status` |
| `DELETE /youtube-music/connection` | `YouTubeMusic\DisconnectYouTubeMusicController` | `youtube-music.disconnect` |

- **Middleware:** all four use `auth`.
- **JSON 401:** `bootstrap/app.php` adds `youtube-music/connect/status` to the paths that answer a JSON 401. The status response sends `Cache-Control: no-store, private`.
- **Settings page:** the settings query is `GetYouTubeMusicSettings`. It returns a `YouTubeMusicSettings` result with `configured`, `status`, `channelName` and `waiting` (`{userCode, verificationUrl}` or null). The controller adds the flashed `youtubeMusicMessage`.
- **Architecture test:** `ArchitectureTest` adds `App\YouTubeMusic\GoogleDeviceFlow` to the classes that entry points must not use.

### D8. The page polls with fetch and reloads on any final status

`settings/YouTubeMusic.vue` follows `settings/Spotify.vue`.

While `waiting` is set, it calls `fetch('/youtube-music/connect/status', {Accept: 'application/json'})` every 2 seconds:

- **`pending`:** it keeps polling.
- **Any other status:** it stops and runs `router.reload()`, so the page shows the flashed message and the new state from the server.
- **401:** it sends the browser to `/login`.

Polling also stops when the page unmounts. The 2 s rate is safe, because the server paces the calls to Google (D3).

The "Connect YouTube Music" button posts with `router.post`. The user code is shown large and selectable. The verification address opens in a new tab with `rel="noopener"`.

### D9. A shared settings link bar

`resources/js/components/settings/SettingsNav.vue` renders two links, "Spotify" and "YouTube Music", with `aria-current="page"` on the current one. It has Aero styles: tokens only, a 44 px touch size and the global focus outline. Both settings pages put it above their panel.

*Alternative rejected:* a tab widget. These are separate pages with their own URLs, so plain links are the correct semantics.

### D10. Shared prop and configuration

- **Shared prop:** `HandleInertiaRequests` adds `youtubeMusic: {status, connected}` from `GetYouTubeMusicStatus`, skipped for guests, as with `spotify`. The types in `resources/js/types/index.ts` add `YouTubeMusicStatus`.
- **Configuration:** `config/services.php` adds `youtube_music.client_id`, `client_secret`, `owner_channel_id` and `timeout` (10). YouTube Music counts as configured only when the first three are all set.
- **Docs:**
  - `.env.example` gets the three keys, empty;
  - the README explains how to create the "TV and Limited Input devices" client;
  - it also explains how to add the owner as a test user, how to find the channel id, and the 7-day limit while the app stays in "Testing".

## Risks / Trade-offs

- [The app stays in "Testing", so the authorization expires after 7 days] → `youtube-music-credentials` turns the `invalid_grant` into `reconnect_required`, and this page already shows that state. Verifying the Google app is a separate task.
- [A poll holds a PHP worker for up to about 35 s under the lock, on shared hosting] → that only happens when the code is approved. Normal polls do nothing until `next_poll_at`, and the page polls only while an attempt is waiting.
- [The `database` cache loses the attempt when the cache is cleared] → the attempt ends early, and the page answers `idle` and shows "Connect YouTube Music" again. Nothing is stored.
- [Google changes the device flow's error codes] → any unknown refusal maps to `Rejected`, never to a 500.

## Migration Plan

1. Deploy, then run `php artisan migrate`, which creates `youtube_music_connections`.
2. Set the three `YOUTUBE_MUSIC_*` keys in the server's `.env` (T-04).
3. Connect from `/settings/youtube-music`.

**Rollback:** roll back the migration, which drops the table. No other data depends on it.

## Implementation notes

- **Configuration test:** task 1.1 asked for a unit test, but `isConfigured()` reads Laravel's configuration. The test lives in `tests/Feature/YouTubeMusic/GoogleDeviceFlowTest.php`.
- **"Connect YouTube Music" while waiting:** the button stays visible while an attempt waits, so the owner can ask for a new code in the same tab ("A new attempt replaces the previous one"). Pressing it replaces the code.
- **Browser tests and `Http::fake()`:** a second `Http::fake()` does not replace the stubs of the first, so `tests/Browser/YouTubeMusicSettingsTest.php` changes the token answer through a property its stub reads.

## Manual checks

**2026-11-15, local app with the owner's Google client (task 6.2):**

- **Connect:** pressing "Connect YouTube Music" showed the code, and after approval with the owner's account the page moved to "YouTube Music connected" with the channel name by itself. The stored connection has the status `connected`, only the scope `youtube.readonly` and a refresh token.
- **Disconnect:** "Disconnect YouTube Music" deleted the connection. Google's third-party access page showed no entry named after the app before or after the disconnect, so the revocation could not be confirmed by eye; the automated tests cover the revocation request.
- **Not checked by hand:** the page source and connecting again. The automated tests cover both ("The settings page holds no tokens" and "Reconnecting replaces the connection").

