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

### Requirement: The browser player is created only with Spotify connected
On every signed-in page with Spotify connected, the system SHALL load Spotify's Web Playback SDK and create exactly one player named after the application, at the remembered volume or 50 %, and connect it. Without a connection it SHALL NOT load the SDK, and the player bar SHALL show "Connect Spotify to play your music." with a link to "/settings/spotify".

#### Scenario: The SDK player is created with Spotify connected
- **GIVEN** Spotify connected, APP_NAME "Music", nothing remembered in the browser and the fake SDK
- **WHEN** the owner opens a signed-in page
- **THEN** exactly one "Spotify.Player" is created with name "Music" and volume 0.5
- **AND** "connect()" is called

#### Scenario: Spotify not connected
- **GIVEN** the owner signed in and Spotify not connected
- **WHEN** the owner opens "/dashboard"
- **THEN** the SDK script is not requested and no "Spotify.Player" is created
- **AND** the player bar shows "Connect Spotify to play your music." with a link to "/settings/spotify"

#### Scenario: The player bar is on every signed-in page
- **GIVEN** the owner signed in
- **WHEN** the owner opens "/dashboard", "/styleguide" and "/settings/spotify"
- **THEN** each page shows the app's player bar
- **AND** "/login" shows no player bar

### Requirement: The SDK gets its token from the server
When the SDK asks for a token, the browser player SHALL request "GET /spotify/token" as a JSON request and pass the returned access token to the SDK.

#### Scenario: The SDK asks the backend for a token
- **GIVEN** the player created with the fake SDK and Spotify connected with the access token "tok-456"
- **WHEN** the SDK calls its "getOAuthToken" callback
- **THEN** the browser requests "GET /spotify/token"
- **AND** the callback receives "tok-456"

### Requirement: Controls wait for the device
The player bar's "Play", "Previous" and "Next" buttons SHALL stay disabled until the SDK reports the device ready.

#### Scenario: Controls disabled until the device is ready
- **GIVEN** the player created and the fake SDK not yet ready
- **WHEN** the bar loads
- **THEN** "Play", "Previous" and "Next" are disabled
- **WHEN** the SDK emits "ready" with device_id "web-1"
- **THEN** "Play" is enabled

### Requirement: Features start playback through the player function
The browser player SHALL offer a function play({contextUri?, uris?, offset?}) that calls the SDK's "activateElement()" and then sends "PUT /spotify/player/play" with the ready device, exactly one of the context URI or the track URIs, and the offset, position 0 by default. With neither or both, it SHALL reject without any request.

#### Scenario Outline: Starting playback from the player function
- **GIVEN** the fake SDK ready with device_id "web-1" and the Web API answering 204
- **WHEN** play(<arguments>) is called
- **THEN** "activateElement()" is called before the request
- **AND** Spotify receives "PUT /v1/me/player/play?device_id=web-1" with <body>

##### Examples
| arguments | body |
| {contextUri: "spotify:album:abc", offset: {position: 0}} | context_uri "spotify:album:abc" and offset {"position": 0} |
| {contextUri: "spotify:album:abc", offset: {uri: "spotify:track:t2"}} | context_uri "spotify:album:abc" and offset {"uri": "spotify:track:t2"} |
| {uris: ["spotify:track:t1"]} | uris ["spotify:track:t1"] and offset {"position": 0} |

#### Scenario: The player function rejects invalid arguments
- **GIVEN** the fake SDK ready with device_id "web-1"
- **WHEN** play({}) or play with both contextUri and uris is called
- **THEN** the returned promise rejects with an error saying exactly one of contextUri or uris is needed
- **AND** no "PUT /spotify/player/play" request is sent

### Requirement: The bar shows what is playing
For the latest SDK state, the player bar SHALL show the track title, the artists joined by ", ", the cover, the elapsed and total time, and "Pause" while playing or "Play" while paused. "Next" SHALL be disabled when the state has no next track.

#### Scenario: The bar shows the current track
- **GIVEN** the fake SDK ready
- **WHEN** the SDK emits "player_state_changed" with "Track A" by "Artist 1" and "Artist 2", a cover, duration 180000 ms, position 10000 ms and paused false
- **THEN** the bar shows "Track A", "Artist 1, Artist 2", that cover, "0:10" and "3:00"
- **AND** the play button is labelled "Pause"

#### Scenario: No next track
- **GIVEN** the fake SDK with "Track C" playing and no next tracks
- **WHEN** the bar loads
- **THEN** "Next" is disabled
- **WHEN** the owner presses "Next"
- **THEN** "nextTrack()" is not called

### Requirement: The progress follows playback
Between SDK states, the player bar SHALL advance the elapsed time while the track plays and keep it still while paused.

#### Scenario: The progress advances while playing
- **GIVEN** the fake SDK emitted a state with position 10000 ms and paused false
- **WHEN** 2 seconds pass without new SDK events
- **THEN** the bar shows "0:12", within 1 second

#### Scenario: The progress stops while paused
- **GIVEN** the fake SDK emitted a state with position 10000 ms and paused true
- **WHEN** 2 seconds pass without new SDK events
- **THEN** the bar still shows "0:10"
- **AND** the play button is labelled "Play"

### Requirement: A blocked autoplay asks to press play
When the SDK reports that the browser blocked autoplay, the player bar SHALL show "Press play to start listening", and pressing "Play" SHALL call "activateElement()" and then "resume()".

#### Scenario: The browser blocks autoplay
- **GIVEN** the fake SDK ready
- **WHEN** the SDK emits "autoplay_failed"
- **THEN** the bar shows "Press play to start listening"
- **WHEN** the owner presses "Play"
- **THEN** "activateElement()" is called and then "resume()"

### Requirement: The bar controls drive the player
The player bar SHALL pause and resume, seek, change the volume and move to the next or previous track through the SDK. "Previous" SHALL restart the track when more than 3 seconds have elapsed, and move to the previous track otherwise. A seek SHALL show its new position at once. The volume control SHALL range 0 to 100 %, report "N%" and pass it to the SDK as 0 to 1.

#### Scenario: Pause and resume
- **GIVEN** the fake SDK with a track playing
- **WHEN** the owner presses "Pause"
- **THEN** "pause()" is called
- **WHEN** the SDK emits a paused state and the owner presses "Play"
- **THEN** "resume()" is called

#### Scenario: Change the volume
- **GIVEN** the fake SDK with a track playing
- **WHEN** the owner sets the volume control to 30 %
- **THEN** "setVolume(0.3)" is called
- **AND** the volume control reports "30%"

#### Scenario: Seek within the track
- **GIVEN** the fake SDK with a 180000 ms track playing
- **WHEN** the owner moves the progress control to 1:30
- **THEN** "seek(90000)" is called
- **AND** the bar shows "1:30"

#### Scenario: Next and previous within the context
- **GIVEN** the fake SDK with a state that has previous and next tracks, 1 second into the track
- **WHEN** the owner presses "Next"
- **THEN** "nextTrack()" is called
- **WHEN** the owner presses "Previous"
- **THEN** "previousTrack()" is called

#### Scenario: Previous restarts a track after 3 seconds
- **GIVEN** the fake SDK with a track playing 10 seconds in
- **WHEN** the owner presses "Previous"
- **THEN** "seek(0)" is called and "previousTrack()" is not
- **AND** the bar shows "0:00"

### Requirement: The volume is remembered in the browser
The browser player SHALL remember the last volume the owner set and use it for the next player it creates in that browser. Without a remembered value, the volume SHALL start at 50 %.

#### Scenario: The volume survives a reload
- **GIVEN** the owner set the volume to 30 % with the fake SDK
- **WHEN** the owner reloads the page
- **THEN** the new "Spotify.Player" is created with volume 0.3
- **AND** the volume control reports "30%"

### Requirement: Playback survives navigation
Moving between signed-in pages SHALL keep the same player and the bar's state: the player SHALL NOT be created again or disconnected.

#### Scenario: The music keeps playing across pages
- **GIVEN** the fake SDK ready and a state with "Track A"
- **WHEN** the owner navigates to another signed-in page of the app
- **THEN** "Spotify.Player" has been created once and "disconnect()" has not been called
- **AND** the bar still shows "Track A"

### Requirement: Leaving stops the player
Logging out or disconnecting Spotify SHALL disconnect the SDK player, and after disconnecting Spotify the bar SHALL show "Connect Spotify to play your music.". When the token or start endpoint answers 401 or 419, the browser SHALL go to "/login".

#### Scenario: Logging out stops the music
- **GIVEN** the fake SDK with a track playing
- **WHEN** the owner logs out
- **THEN** "disconnect()" is called

#### Scenario: Disconnecting Spotify with music playing
- **GIVEN** the fake SDK with a track playing
- **WHEN** the owner presses "Disconnect Spotify"
- **THEN** "disconnect()" is called
- **AND** the bar shows "Connect Spotify to play your music."

#### Scenario: An expired session sends the owner to sign in
- **GIVEN** the player created with the fake SDK and the session ended in the background
- **WHEN** the SDK calls its "getOAuthToken" callback
- **THEN** the browser goes to "/login"
