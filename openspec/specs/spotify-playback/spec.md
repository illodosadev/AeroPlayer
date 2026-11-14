# spotify-playback Specification

## Purpose
Lets the owner play their Spotify music in the browser tab: the server hands the browser player a fresh access token and starts albums, playlists or tracks on the browser's device, always answering the player in JSON.

## Requirements

### Requirement: The browser player gets a fresh access token
The signed-in owner's browser SHALL obtain a valid Spotify access token and its remaining lifetime in seconds from "GET /spotify/token", refreshed by the system when needed. The response SHALL be JSON with "Cache-Control: no-store" and SHALL NOT contain the refresh token.

#### Scenario: The endpoint hands over the current token
- **GIVEN** Spotify connected with the access token "tok-123", valid for 3000 more seconds, and the refresh token "AQ-refresh-456"
- **WHEN** the signed-in owner requests "GET /spotify/token"
- **THEN** the response has status 200 and the JSON {"access_token": "tok-123", "expires_in": 3000}
- **AND** the "Cache-Control" header contains "no-store"
- **AND** the response body does not contain "AQ-refresh-456"

#### Scenario: An expiring token is refreshed before it is handed over
- **GIVEN** Spotify connected with an access token that expires in 30 seconds
- **AND** Spotify answers the refresh with the access token "tok-456" and expires_in 3600
- **WHEN** the signed-in owner requests "GET /spotify/token"
- **THEN** the response has status 200 and the JSON {"access_token": "tok-456", "expires_in": 3600}

### Requirement: The token endpoint answers failures in JSON
When "GET /spotify/token" cannot hand over a token, it SHALL answer JSON with an "error" code and no token: 401 "unauthenticated" for a JSON request without a session, 409 "spotify_not_connected", 409 "spotify_reconnect_required", 429 "rate_limited" with "retry_after" in seconds, and 502 "spotify_unavailable".

#### Scenario Outline: The token endpoint cannot hand over a token
- **GIVEN** <situation>
- **WHEN** "GET /spotify/token" is requested as a JSON request
- **THEN** the response has status <status>
- **AND** the JSON has "error" = "<code>" and contains no token

##### Examples
| situation | status | code |
| a visitor without a session | 401 | unauthenticated |
| the owner signed in and Spotify not connected | 409 | spotify_not_connected |
| Spotify connected and the refresh rejected with "invalid_grant" | 409 | spotify_reconnect_required |
| Spotify connected and the token endpoint answering 503 | 502 | spotify_unavailable |

#### Scenario: The token endpoint during the rate-limit pause
- **GIVEN** Spotify connected and the shared rate-limit pause active with 5 seconds left
- **WHEN** the signed-in owner requests "GET /spotify/token" as a JSON request
- **THEN** the response has status 429 and the JSON has "error" = "rate_limited" and "retry_after" = 5
- **AND** no request is sent to Spotify

### Requirement: Playback starts on the browser's device
"PUT /spotify/player/play" SHALL start playback on the given device with exactly one of a context URI (album, playlist or artist) or a list of track URIs, and an offset by position or by URI that defaults to position 0. It SHALL send the matching Web API request with the owner's access token and answer 204.

#### Scenario Outline: Starting playback on the web device
- **GIVEN** Spotify connected and the Web API answering 204
- **WHEN** the browser sends "PUT /spotify/player/play" with device_id "web-1" and <input>
- **THEN** "PUT https://api.spotify.com/v1/me/player/play?device_id=web-1" is sent with the body <body>
- **AND** that request carries the access token as "Authorization: Bearer"
- **AND** the response to the browser has status 204

##### Examples
| input | body |
| context_uri "spotify:album:abc" and offset {"position": 0} | {"context_uri": "spotify:album:abc", "offset": {"position": 0}} |
| context_uri "spotify:album:abc" and offset {"uri": "spotify:track:t2"} | {"context_uri": "spotify:album:abc", "offset": {"uri": "spotify:track:t2"}} |
| uris ["spotify:track:t1"] without offset | {"uris": ["spotify:track:t1"], "offset": {"position": 0}} |
| uris ["spotify:track:t1", "spotify:track:t2"] and offset {"position": 1} | {"uris": ["spotify:track:t1", "spotify:track:t2"], "offset": {"position": 1}} |

### Requirement: A malformed start request is rejected
"PUT /spotify/player/play" SHALL answer 422 without sending anything to Spotify when the device is missing, when neither or both of the context URI and the track URIs are given, or when the offset is neither a position of 0 or more nor a URI.

#### Scenario Outline: A malformed start request
- **GIVEN** Spotify connected
- **WHEN** the browser sends "PUT /spotify/player/play" as a JSON request with <input>
- **THEN** the response has status 422
- **AND** no request is sent to Spotify

##### Examples
| input |
| device_id "web-1" and neither context_uri nor uris |
| device_id "web-1", context_uri "spotify:album:abc" and uris ["spotify:track:t1"] |
| context_uri "spotify:album:abc" without device_id |
| device_id "web-1", context_uri "spotify:album:abc" and offset {"position": -1} |

### Requirement: Playback start failures answer in JSON
When playback cannot start, "PUT /spotify/player/play" SHALL answer JSON with an "error" code, never an HTML page. The codes are 401 "unauthenticated", 419 "session_expired" for an expired session, 409 "spotify_not_connected" or "spotify_reconnect_required", 403 "premium_required", 404 "device_not_found", 429 "rate_limited" with "retry_after", 502 "spotify_unavailable" and 422 "playback_rejected".

#### Scenario Outline: Playback start failures
- **GIVEN** <situation>
- **WHEN** the browser sends "PUT /spotify/player/play" as a JSON request with device_id "web-1" and context_uri "spotify:album:abc"
- **THEN** the response has status <status> and the JSON has "error" = "<code>"<extra>
- **AND** the response is not an HTML error page

##### Examples
| situation | status | code | extra |
| a visitor without a session | 401 | unauthenticated | |
| the owner signed in and Spotify not connected | 409 | spotify_not_connected | |
| Spotify connected and the refresh rejected with "invalid_grant" | 409 | spotify_reconnect_required | |
| Spotify answering 403 with reason "PREMIUM_REQUIRED" | 403 | premium_required | |
| Spotify answering 404 "Device not found" | 404 | device_not_found | |
| Spotify answering 429 with "Retry-After: 7" | 429 | rate_limited | and "retry_after" = 7 |
| the shared rate-limit pause active with 5 seconds left, without calling Spotify | 429 | rate_limited | and "retry_after" = 5 |
| Spotify answering 503 | 502 | spotify_unavailable | |
| the Web API not answering before the timeout | 502 | spotify_unavailable | |
| Spotify answering 400 for an unknown context | 422 | playback_rejected | |

#### Scenario: An expired session on the start endpoint
- **GIVEN** a browser tab whose session expired, so its CSRF token no longer matches
- **WHEN** it sends "PUT /spotify/player/play" as a JSON request with device_id "web-1" and context_uri "spotify:album:abc"
- **THEN** the response has status 419 and the JSON has "error" = "session_expired"
- **AND** no request is sent to Spotify
