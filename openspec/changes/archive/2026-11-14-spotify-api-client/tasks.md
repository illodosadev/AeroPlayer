# Tasks

Every test fakes Spotify with `Http::preventStrayRequests()` and `Http::fake()`, and moves the clock with `travel()`/`travelTo()`. No test reaches Spotify.

## 1. Failures and storage

- [x] 1.1 Create the typed failures in `app/Application/Spotify/Exceptions` (design D2): the abstract `SpotifyApiFailed extends UseCaseFailed`, plus `SpotifyNotConnected`, `SpotifyReconnectRequired`, `SpotifyUnavailable` and `SpotifyRateLimited(int $retryAfter)`, each with a fixed message. Verify that the reflection test in `tests/Unit/ArchitectureTest.php` accepts them.
- [x] 1.2 Add `storeRefreshedTokens(string $connectionId, string $accessToken, ?string $refreshToken, int $expiresIn)` and `markReconnectRequired(string $connectionId)` to `SpotifyConnectionRepository` (design D6). Verify them in `tests/Feature/Spotify/SpotifyConnectionRepositoryTest.php`:
  - the new access token and its expiry are stored;
  - a null refresh token keeps the old one;
  - marking nulls both tokens and the expiry and sets `reconnect_required`;
  - both methods leave a connection with another `connection_id` unchanged;
  - by reflection, the token parameters of `storeRefreshedTokens()` carry `#[\SensitiveParameter]` (design D7).
- [x] 1.3 Add `SpotifyOAuth::requestRefresh(string $refreshToken): Response` (design D4): `grant_type=refresh_token`, HTTP Basic client credentials and the configured timeout, without `->throw()`. Verify in `tests/Feature/Spotify/SpotifyOAuthRefreshTest.php` that the request carries the grant, the refresh token and the Basic credentials, and, by reflection, that its parameter carries `#[\SensitiveParameter]` (design D7).

## 2. The client

- [x] 2.1 Create `App\Spotify\SpotifyClient` with `get()`, `send()` and `accessToken()`, covering the connection checks, the refresh before expiry and the plain call (design D1, D3, D4). Verify in `tests/Feature/Spotify/SpotifyClientTest.php` the scenarios:
  - "Calling without a connection";
  - "Calling with a connection that needs reconnecting";
  - "An expired token is refreshed";
  - "Spotify rotates the refresh token";
  - "A token expiring within 60 seconds is refreshed first";
  - "A valid token is not refreshed";
  - a stored token without an expiry is refreshed (design D3);
  - "A 403 reaches the feature";
  - `accessToken()` returns the refreshed token.
- [x] 2.2 Add the refresh lock (design D4). Verify "A refresh already done by another call is reused" with the connection expired when the call starts. The test swaps `Cache::lock('spotify:token-refresh', …)`, through a Mockery proxy around the real cache manager (design D4), for a fake lock whose `block()` first stores `BQ-fresh-111`, valid for 3600 s, and then runs the callback. The call must then send no refresh and use `BQ-fresh-111`. Also verify:
  - the same re-read after a 401, where a token changed by "the other call" is used without a refresh;
  - a lock that never frees fails as `SpotifyUnavailable`. The test holds the real lock and uses `Sleep::fake(syncWithCarbon: true)`, so the 10 s timeout passes instantly and the test cannot hang on a frozen clock (design D4).
- [x] 2.3 Add the 401 retry, `invalid_grant` and the decrypt failure (design D3, D4, D6). Verify:
  - "A 401 refreshes the token and retries";
  - "A 401 after the refresh asks to reconnect";
  - "Access revoked on Spotify or refresh token expired", including the settings page text;
  - "Tokens encrypted with a previous application key", with tokens written raw with another key;
  - "A failed refresh exposes no secret", which checks the exception message and listens to `MessageLogged` and reports the failure, then asserts that no logged message, the failure message or its trace contains the refresh token, the access token or the client secret. It does not assert an empty log, because PHP 8.5 deprecation notices may reach it (design D7).
- [x] 2.4 Add unavailability and the shared pause (design D3, D5). Verify:
  - "Spotify down during the refresh";
  - "Spotify does not answer in time", with a `ConnectionException`;
  - "The Web API answers 5xx";
  - "A 429 with Retry-After";
  - "The pause is shared and sends nothing";
  - "A 429 without Retry-After";
  - "A 429 from the token endpoint starts the pause too";
  - "No refresh during the pause";
  - a non-numeric `Retry-After` falls back to 5 seconds;
  - an `invalid_client` answer to the refresh fails as `SpotifyUnavailable` and keeps the tokens.

## 3. Rules and documentation

- [x] 3.1 Add `App\Spotify\SpotifyClient` to the entry-point expectation in `tests/Unit/ArchitectureTest.php`, and verify that the arch test passes. In the README's "Spotify" section, document:
  - that the cache store must be shared between processes (`database`, never `array` in a deployment), for the refresh lock and the rate-limit pause;
  - that `APP_PREVIOUS_KEYS` keeps the tokens readable when `APP_KEY` changes, while a lost key means reconnecting.

  Verify that the README names no real ids or secrets.

## 4. Integration

- [x] 4.1 Run Pint on the changed PHP files. Then verify that `openspec validate spotify-api-client --strict`, `npm run build` and the full `vendor/bin/pest` run pass together.

## Workflow follow-up

- `/review-change spotify-api-client` before `/opsx-apply`.
- At `/ship`:
  - merge the `spotify-web-api` spec;
  - set T-05 to `status: done`;
  - apply the backlog follow-ups from design (T-06, T-07, T-08, T-15, T-23, T-24).
