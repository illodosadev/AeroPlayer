# Spec Delta

## ADDED Requirements

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
