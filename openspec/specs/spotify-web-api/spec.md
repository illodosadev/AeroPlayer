# spotify-web-api Specification

## Purpose
Lets AeroPlayer's features call the Spotify Web API on the owner's behalf with the stored connection, keeping the access token fresh and turning every Spotify problem into one of four typed failures instead of an error page.

## Requirements

### Requirement: Web API calls need a usable connection
A Web API call SHALL fail as "not connected" when there is no Spotify connection, and as "reconnect required" when the connection is marked for reconnecting or has no tokens. In both cases the system SHALL send nothing to Spotify.

#### Scenario: Calling without a connection
- **GIVEN** no Spotify connection exists
- **WHEN** a feature calls "GET https://api.spotify.com/v1/me"
- **THEN** no request is sent to Spotify
- **AND** the call fails as "not connected"

#### Scenario: Calling with a connection that needs reconnecting
- **GIVEN** a connection of "owner-123" marked "reconnect_required" and without tokens
- **WHEN** a feature calls "GET https://api.spotify.com/v1/me"
- **THEN** no request is sent to Spotify
- **AND** the call fails as "reconnect required"

### Requirement: Access tokens are refreshed before they expire
Before a call, the system SHALL refresh the access token when it expires in less than 60 seconds, send the call with the new token and store the new token and its expiry. It SHALL store a refresh token Spotify rotates, keep the current one when Spotify sends none, and not refresh a token valid for 60 seconds or more.

#### Scenario: An expired token is refreshed
- **GIVEN** Spotify connected with an access token that expired 10 seconds ago and the refresh token "AQ-refresh-456"
- **AND** Spotify answers the refresh with the access token "BQ-new-789" and expires_in 3600, without a new refresh token
- **WHEN** a feature calls "GET https://api.spotify.com/v1/me"
- **THEN** exactly one request with grant_type=refresh_token and the refresh token "AQ-refresh-456" is sent
- **AND** the call to "/v1/me" carries "Authorization: Bearer BQ-new-789"
- **AND** the stored connection holds "BQ-new-789", expiring in 3600 seconds, and the refresh token "AQ-refresh-456"

#### Scenario: Spotify rotates the refresh token
- **GIVEN** Spotify connected with an expired access token
- **AND** Spotify answers the refresh with the access token "BQ-new-789" and the refresh token "AQ-rotated-999"
- **WHEN** a feature calls the Web API
- **THEN** the stored connection holds the refresh token "AQ-rotated-999"

#### Scenario: A token expiring within 60 seconds is refreshed first
- **GIVEN** Spotify connected with an access token that expires in 30 seconds
- **WHEN** a feature calls the Web API
- **THEN** the refresh request is sent before the Web API request

#### Scenario: A valid token is not refreshed
- **GIVEN** Spotify connected with an access token that expires in 30 minutes
- **WHEN** a feature calls the Web API
- **THEN** no request is sent to Spotify's token endpoint

### Requirement: Parallel calls refresh the token only once
While one call refreshes the access token, another call that also finds it expiring SHALL wait for that refresh and use its result instead of sending a second refresh, so a rotated refresh token is never lost.

#### Scenario: A refresh already done by another call is reused
- **GIVEN** Spotify connected with an expired access token
- **AND** another call holds the refresh in progress and stores the access token "BQ-fresh-111", valid for 3600 seconds, before releasing it
- **WHEN** a feature calls "GET https://api.spotify.com/v1/me"
- **THEN** no request is sent to Spotify's token endpoint
- **AND** the call to "/v1/me" carries "Authorization: Bearer BQ-fresh-111"

### Requirement: A rejected token is refreshed and the call retried once
When the Web API answers 401, the system SHALL refresh the access token and repeat the call once with the new token. When the repeated call also answers 401, the call SHALL fail as "reconnect required", and the connection SHALL be marked "reconnect_required" with its tokens deleted.

#### Scenario: A 401 refreshes the token and retries
- **GIVEN** Spotify connected with the valid access token "BQ-access-123"
- **AND** Spotify answers the first "GET https://api.spotify.com/v1/me" with 401 and the second with 200
- **AND** Spotify answers the refresh with the access token "BQ-new-789"
- **WHEN** a feature calls "GET https://api.spotify.com/v1/me"
- **THEN** one refresh is sent and the call is repeated once, with "Authorization: Bearer BQ-new-789"
- **AND** the feature receives the 200 response

#### Scenario: A 401 after the refresh asks to reconnect
- **GIVEN** Spotify connected
- **AND** Spotify answers every "GET https://api.spotify.com/v1/me" with 401 and the refresh with success
- **WHEN** a feature calls "GET https://api.spotify.com/v1/me"
- **THEN** exactly 2 requests to "/v1/me" and 1 refresh are sent
- **AND** the call fails as "reconnect required"
- **AND** the connection is marked "reconnect_required" and has no tokens

### Requirement: Revoked or unreadable access asks to reconnect
When Spotify rejects the refresh as "invalid_grant", or when the stored tokens can no longer be decrypted, the call SHALL fail as "reconnect required", and the connection SHALL be marked "reconnect_required" with its tokens deleted. The Spotify settings page SHALL then ask the owner to reconnect.

#### Scenario: Access revoked on Spotify or refresh token expired
- **GIVEN** Spotify connected with an expired access token
- **AND** Spotify answers the refresh with status 400 and the error "invalid_grant"
- **WHEN** a feature calls the Web API
- **THEN** the call fails as "reconnect required"
- **AND** the connection is marked "reconnect_required" and has no tokens
- **AND** "GET /settings/spotify" answers 200 and shows "Your Spotify access expired or was revoked. Please reconnect Spotify." and the "Connect Spotify" button

#### Scenario: Tokens encrypted with a previous application key
- **GIVEN** Spotify connected, with tokens encrypted by an application key that is no longer configured
- **WHEN** a feature calls the Web API
- **THEN** no request is sent to Spotify
- **AND** the call fails as "reconnect required"
- **AND** the connection is marked "reconnect_required" and has no tokens

### Requirement: Spotify not responding leaves the tokens untouched
When the Web API or the token endpoint answers 5xx, cannot be reached or does not answer within 10 seconds, the call SHALL fail as "unavailable" and the stored tokens SHALL not change.

#### Scenario: Spotify down during the refresh
- **GIVEN** Spotify connected with an expired access token
- **AND** Spotify answers the refresh with status 503
- **WHEN** a feature calls the Web API
- **THEN** the call fails as "unavailable"
- **AND** the stored connection holds the same tokens as before

#### Scenario: Spotify does not answer in time
- **GIVEN** Spotify connected
- **AND** "GET https://api.spotify.com/v1/me" cannot be reached before the timeout
- **WHEN** a feature calls it
- **THEN** the call fails as "unavailable"
- **AND** the stored tokens do not change

#### Scenario: The Web API answers 5xx
- **GIVEN** Spotify connected
- **AND** Spotify answers "GET https://api.spotify.com/v1/me" with status 502
- **WHEN** a feature calls it
- **THEN** the call fails as "unavailable"

### Requirement: A rate limit pauses every Spotify request
A 429 from the Web API or the token endpoint SHALL start one pause shared by every feature, lasting the "Retry-After" seconds, or 5 seconds without that header. The call SHALL fail as "rate limited" with the wait. During the pause no request SHALL reach Spotify, refreshes included, and calls fail with the remaining wait.

#### Scenario: A 429 with Retry-After
- **GIVEN** Spotify connected
- **AND** Spotify answers "GET https://api.spotify.com/v1/me/playlists" with status 429 and "Retry-After: 5"
- **WHEN** a feature calls it
- **THEN** the call fails as "rate limited" with a wait of 5 seconds

#### Scenario: The pause is shared and sends nothing
- **GIVEN** a call received a 429 with "Retry-After: 5" on "/v1/me/playlists" 2 seconds ago
- **WHEN** a feature calls "GET https://api.spotify.com/v1/search?q=daft+punk&type=track"
- **THEN** no request is sent to Spotify
- **AND** the call fails as "rate limited" with a wait of 3 seconds
- **AND** 6 seconds after the 429 the same call is sent

#### Scenario: A 429 without Retry-After
- **GIVEN** Spotify connected
- **AND** Spotify answers with status 429 without a "Retry-After" header
- **WHEN** a feature calls the Web API
- **THEN** the call fails as "rate limited" with a wait of 5 seconds
- **AND** for those 5 seconds no request is sent to Spotify

#### Scenario: A 429 from the token endpoint starts the pause too
- **GIVEN** Spotify connected with an expired access token
- **AND** Spotify answers the refresh with status 429 and "Retry-After: 5"
- **WHEN** a feature calls "GET https://api.spotify.com/v1/me"
- **THEN** the call fails as "rate limited" with a wait of 5 seconds
- **AND** no request is sent to "/v1/me"
- **AND** the stored tokens do not change

#### Scenario: No refresh during the pause
- **GIVEN** Spotify connected with an expired access token
- **AND** a call received a 429 with "Retry-After: 5" 2 seconds ago
- **WHEN** a feature calls the Web API
- **THEN** no request is sent to the token endpoint or the Web API
- **AND** the call fails as "rate limited" with a wait of 3 seconds

### Requirement: Other Web API answers reach the caller
Any Web API answer that is not a 401, a 429 or a 5xx SHALL reach the calling feature unchanged, including 2xx, 403 and 404, so each feature decides what it means.

#### Scenario: A 403 reaches the feature
- **GIVEN** Spotify connected
- **AND** Spotify answers "GET https://api.spotify.com/v1/me/player" with status 403
- **WHEN** a feature calls it
- **THEN** the feature receives the 403 response
- **AND** the connection keeps its tokens and its "connected" status

### Requirement: Web API failures carry no secrets
The system SHALL NOT log Spotify tokens, the client secret or token endpoint responses while calling the Web API, and its failures SHALL NOT contain any of them.

#### Scenario: A failed refresh exposes no secret
- **GIVEN** Spotify connected with an expired access token and the refresh token "AQ-refresh-456"
- **AND** Spotify answers the refresh with status 400, the error "invalid_grant" and a body containing "AQ-refresh-456"
- **WHEN** a feature calls the Web API and the call fails
- **THEN** the failure's message contains neither "AQ-refresh-456", nor the access token, nor the client secret
- **AND** the application log contains none of them
