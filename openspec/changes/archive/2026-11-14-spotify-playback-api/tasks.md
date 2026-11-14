# Tasks

Every test fakes Spotify with `Http::preventStrayRequests()` and `Http::fake()`. No test reaches Spotify.

## 1. Groundwork

- [x] 1.1 Make `SpotifyClient::accessToken()` return `App\Spotify\SpotifyAccessToken(string $value, int $expiresIn)` (design D2). Its token parameter carries `#[\SensitiveParameter]`, and `expiresIn` is the whole number of seconds left, never negative. Update the `accessToken` test in `tests/Feature/Spotify/SpotifyClientTest.php` to check the value and the lifetime, and verify that the client tests pass.
- [x] 1.2 In `bootstrap/app.php` (design D6), render:
  - `AuthenticationException` as JSON 401 `{"error": "unauthenticated"}` for JSON requests to `spotify/token` and `spotify/player/*`;
  - `TokenMismatchException` as JSON 419 `{"error": "session_expired"}` for JSON requests to `spotify/player/*`.

  Verify in `tests/Feature/Spotify/SpotifyPlaybackAuthTest.php`, through the exception handler, because the routes only exist from steps 2.1 and 3.2 and Laravel skips CSRF in tests:
  - a guest's JSON request to either path renders the JSON 401;
  - "An expired session on the start endpoint" renders the JSON 419;
  - other routes, and non-JSON requests, keep Laravel's answers.

  Also verify that `OwnerAuthTest` and `ErrorPagesTest` pass unchanged. The HTTP-level 401 of each endpoint, and the redirect of a guest's plain GET of `/spotify/token`, are checked in steps 2.1 and 3.2.

## 2. Token endpoint

- [x] 2.1 Add the query `GetSpotifyPlaybackToken` with `Results\SpotifyPlaybackToken`, `Spotify/ShowSpotifyPlaybackTokenController`, the trait `Spotify\Concerns\RespondsWithSpotifyErrors` with the client's four failures (design D2, D5), and the route `GET spotify/token` named `spotify.token`. Add the route to the table in `tests/Feature/RoutesTest.php`. Verify in `tests/Feature/Spotify/SpotifyPlaybackTokenTest.php`:
  - "The endpoint hands over the current token", including `no-store` and the absence of the refresh token;
  - "An expiring token is refreshed before it is handed over";
  - the outline "The token endpoint cannot hand over a token" for the 409 and 502 rows;
  - "The token endpoint during the rate-limit pause".

## 3. Start endpoint

- [x] 3.1 Add `SpotifyPremiumRequired`, `SpotifyDeviceNotFound` and `SpotifyPlaybackRejected`, extending `SpotifyApiFailed`, and map them in `RespondsWithSpotifyErrors` (design D3, D5). Verify that the reflection test in `tests/Unit/ArchitectureTest.php` accepts them.
- [x] 3.2 Add `StartSpotifyPlaybackRequest` (design D4), the command `StartSpotifyPlayback` with its handler (design D3), `Spotify/StartSpotifyPlaybackController`, and the route `PUT spotify/player/play` named `spotify.player.play`, also added to `RoutesTest`. Verify in `tests/Feature/Spotify/StartSpotifyPlaybackTest.php`:
  - the four rows of "Starting playback on the web device", checking URL, body and `Authorization`. The row without `offset` depends on the `sometimes` note in design D4;
  - the four rows of "A malformed start request", each with nothing sent to Spotify;
  - every row of "Playback start failures" except the 401, which step 1.2 covers. Each answer must be JSON, not HTML.

## 4. Integration

- [x] 4.1 Run Pint on the changed PHP files. Then verify that `openspec validate spotify-playback-api --strict`, `npm run build` and the full `vendor/bin/pest` run pass together.

## Workflow follow-up

- `/review-change spotify-playback-api` before `/opsx-apply`.
- At `/ship`:
  - merge the `spotify-playback` spec;
  - keep T-06 open;
  - apply the backlog follow-ups from design (T-06, T-23).
- Then `/opsx-propose spotify-web-player`.
