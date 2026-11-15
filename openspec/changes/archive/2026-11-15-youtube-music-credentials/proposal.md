# Proposal

Implements backlog task **T-09** (conectar YouTube Music), part 2 of 2, after `youtube-music-connection`. It closes T-09.

## Why

The YouTube Music library (T-12) and every later feature that reads the owner's account need a valid access token, and Google's tokens last an hour. While the Google app stays in "Testing", the authorization itself ends after 7 days. The app must renew tokens without the owner noticing, and when renewing stops working, it must ask the owner to reconnect instead of failing on every page.

## What Changes

- **Shared credentials service:** features ask it for an access token valid for at least 60 seconds. It renews the token first when it expires sooner, stores the new one encrypted, and keeps a refresh token Google rotates.
- **One renewal at a time:** renewals run under a lock, so parallel requests renew once and share the result.
- **Typed failures:**
  - **not connected:** there is no connection;
  - **reconnect required:** Google answers `invalid_grant`, or the stored tokens are missing or cannot be decrypted. The connection is marked `reconnect_required` and its tokens deleted;
  - **unavailable:** a 5xx, a 429, no answer within 10 seconds, or a lock wait that runs out. The connection is left unchanged;
  - **configuration not valid:** Google answers `invalid_client`, or the client is not configured. Reconnecting would not fix that, so the connection stays `connected`, with a configuration-error mark.
- **Settings page:** with the mark set, `/settings/youtube-music` shows "YouTube Music is not configured correctly." The next successful renewal clears the mark.
- **No secrets out:** renewals log nothing, and failures carry no token or Google response body.

## Capabilities

### New Capabilities

- `youtube-music-credentials`: giving the app's features a valid YouTube access token on the owner's behalf, renewing it, and turning each Google problem into a typed failure.

### Modified Capabilities

- `youtube-music-connection`: the settings page shows the configuration error that a refused renewal leaves on the connection.

## Non-goals

- Calling the YouTube Data API itself: the library (T-12) builds its own reader on top of this service.
- Search (T-11) and playback (T-10), which do not use the owner's token (T-27).
- Verifying the Google app so the authorization stops expiring after 7 days (separate task, to be created).
- A shared pause after a 429, as the Spotify client has. Google answers renewals with 429 rarely, so a 429 is just "unavailable".

## Impact

- **Backend:**
  - a migration that adds the configuration-error mark to `youtube_music_connections`;
  - repository methods to store renewed tokens, mark the connection for reconnecting and set the mark;
  - the renewal request in `GoogleDeviceFlow`;
  - the credentials service and four exceptions;
  - the settings query and the architecture rule.
- **Frontend:** the configuration-error message on `settings/YouTubeMusic.vue`.
- **Tests:** feature tests with `Http::fake()`, time travel and a faked lock, plus a browser state for the message.
- **Docs:** a README note on the renewal and the 7-day reconnect. No new dependencies.
