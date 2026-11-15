# youtube-music-connection Specification

## Purpose
Lets the owner connect their own YouTube Music account to AeroPlayer with Google's OAuth device flow, keeps its tokens on the server, and lets the owner see and end that connection from the YouTube Music settings page.

## Requirements

### Requirement: YouTube Music routes require a session
"/settings/youtube-music", "POST /youtube-music/connect" and "DELETE /youtube-music/connection" SHALL require the signed-in owner and redirect a visitor without a session to "/login". "GET /youtube-music/connect/status" SHALL answer a visitor without a session, when the request asks for JSON, with status 401 and a JSON body. None of them SHALL send anything to Google without a session.

#### Scenario: A visitor opens the YouTube Music settings
- **GIVEN** a visitor without a session
- **WHEN** the visitor requests "GET /settings/youtube-music"
- **THEN** the response redirects to "/login"
- **AND** no request is sent to Google

#### Scenario: The app session expires while waiting for approval
- **GIVEN** a connection attempt waiting for approval and the app session expired
- **WHEN** the page requests "GET /youtube-music/connect/status" asking for JSON
- **THEN** the response status is 401 with a JSON body
- **AND** no request is sent to Google

### Requirement: YouTube Music must be configured to connect
When the Google client id, the Google client secret or the owner's YouTube channel id is not configured, the system SHALL treat YouTube Music as not configured. "/settings/youtube-music" SHALL then show "YouTube Music is not configured." and no "Connect YouTube Music" button, and "POST /youtube-music/connect" SHALL redirect to that page without sending anything to Google.

#### Scenario: A YouTube Music setting is missing
- **GIVEN** every YouTube Music setting configured except one of YOUTUBE_MUSIC_CLIENT_ID, YOUTUBE_MUSIC_CLIENT_SECRET or YOUTUBE_MUSIC_OWNER_CHANNEL_ID, which is empty
- **WHEN** the owner opens "/settings/youtube-music", then requests "POST /youtube-music/connect"
- **THEN** the page shows "YouTube Music is not configured." and no "Connect YouTube Music" button
- **AND** the POST redirects to "/settings/youtube-music"
- **AND** no request is sent to Google

### Requirement: Connecting requests a device code
When the owner starts the connection, the system SHALL send Google one device code request with the configured client id and only the scope "https://www.googleapis.com/auth/youtube.readonly", then show the user code, a link to the verification address and "Waiting for you to approve the code…". The device code and the client secret SHALL never reach the browser.

#### Scenario: Starting the connection
- **GIVEN** the owner signed in, YouTube Music configured and not connected
- **AND** Google (faked) answers "POST https://oauth2.googleapis.com/device/code" with the device code "dev-123", the user code "ABCD-EFGH", the verification address "https://www.google.com/device", an interval of 5 and expires_in 1800
- **WHEN** the owner presses "Connect YouTube Music" ("POST /youtube-music/connect")
- **THEN** exactly one request is sent to "https://oauth2.googleapis.com/device/code", with the configured client id and the scope "https://www.googleapis.com/auth/youtube.readonly"
- **AND** the response redirects to "/settings/youtube-music", which responds 200 and shows "ABCD-EFGH", a link to "https://www.google.com/device" and "Waiting for you to approve the code…"
- **AND** neither the page nor its props contain "dev-123" or the client secret

### Requirement: Starting while connected changes nothing
When YouTube Music is already connected, "POST /youtube-music/connect" SHALL redirect to "/settings/youtube-music" without sending anything to Google and without changing the connection.

#### Scenario: Connect requested while connected
- **GIVEN** YouTube Music connected
- **WHEN** the owner requests "POST /youtube-music/connect"
- **THEN** the response redirects to "/settings/youtube-music", which still shows the connection
- **AND** no request is sent to Google

### Requirement: A failed device code request starts nothing
When Google answers the device code request with a server error or a rate limit (429), or does not answer within 10 seconds, the system SHALL show "Couldn't reach Google. Try again in a moment." When Google answers with the error "invalid_client", it SHALL show "YouTube Music is not configured correctly." In both cases the page SHALL respond 200 with the "Connect YouTube Music" button and without Google's response body.

#### Scenario: Google does not answer the device code request
- **GIVEN** the owner signed in, YouTube Music configured and not connected
- **AND** Google answers "POST /device/code" with status 503 or 429, or does not answer within 10 seconds
- **WHEN** the owner presses "Connect YouTube Music"
- **THEN** the response redirects to "/settings/youtube-music", which responds 200 and shows "Couldn't reach Google. Try again in a moment." and the "Connect YouTube Music" button

#### Scenario: Google rejects the client
- **GIVEN** the owner signed in, YouTube Music configured and not connected
- **AND** Google answers "POST /device/code" with status 401, the error "invalid_client" and a body that includes "upstream-body-detail"
- **WHEN** the owner presses "Connect YouTube Music"
- **THEN** the page shows "YouTube Music is not configured correctly." and the "Connect YouTube Music" button
- **AND** the page does not contain "upstream-body-detail"

### Requirement: The server paces the token requests
While an attempt waits for approval, each status request SHALL answer "pending" without error, and the server SHALL call Google's token endpoint only when the attempt's interval has passed since its last call. "slow_down" or a 429 SHALL add 5 seconds to the interval. A server error or no answer within 10 seconds SHALL keep the attempt waiting with the same interval.

#### Scenario: The server respects Google's interval
- **GIVEN** a connection attempt started with an interval of 5 seconds and Google answering "authorization_pending"
- **WHEN** the page requests the status at 0, 2, 5 and 7 seconds
- **THEN** the server has called Google's token endpoint exactly twice, at 0 and at 5 seconds
- **AND** every status request answers "pending"

#### Scenario: Google asks to slow down
- **GIVEN** a connection attempt started with an interval of 5 seconds
- **AND** Google answered "slow_down" to the call at 0 seconds
- **WHEN** the page requests the status at 5, 9 and 10 seconds
- **THEN** the server calls the token endpoint again only at 10 seconds

#### Scenario: The token endpoint fails while waiting
- **GIVEN** a connection attempt started with an interval of 5 seconds
- **WHEN** Google answers the call at 0 seconds with 503, with no answer within 10 seconds, or with 429
- **THEN** the status request answers "pending"
- **AND** the next call to the token endpoint is at 5 seconds after a 503 or no answer, and at 10 seconds after a 429

### Requirement: The owner's account connects
When the owner approves the code, the system SHALL read the account's channel from the YouTube Data API. When the channel id equals the configured owner channel id, it SHALL store the connection, stop calling the token endpoint for that attempt, answer the status "connected" and show "YouTube Music connected" with the channel name.

#### Scenario: Connecting the owner's account
- **GIVEN** YOUTUBE_MUSIC_OWNER_CHANNEL_ID="UC-owner" and a connection attempt waiting for approval
- **AND** Google (faked) answers the token endpoint with the access token "ya29.access-123", the refresh token "1//refresh-456", expires_in 3599 and the granted scope
- **AND** Google (faked) answers "GET https://www.googleapis.com/youtube/v3/channels" with the channel id "UC-owner" and the title "Alice Music"
- **WHEN** the page requests the status
- **THEN** the channel request carries "part=snippet" and "mine=true"
- **AND** the status answers "connected"
- **AND** "/settings/youtube-music" shows "YouTube Music connected" and "Alice Music"
- **AND** there is exactly one connection, for the channel "UC-owner", with the granted scope and an access token expiry 3599 seconds from now
- **AND** a later status request sends nothing to the token endpoint

### Requirement: Only the owner's YouTube account is accepted
When the approved account's channel differs from the configured owner channel, or the account has no channel, the system SHALL store nothing, leave any existing connection unchanged, try to revoke the tokens it received, answer the status "not_owner" and show "This YouTube account isn't yours. Nothing was connected." with the "Connect YouTube Music" button.

#### Scenario: Another account approves the code
- **GIVEN** YOUTUBE_MUSIC_OWNER_CHANNEL_ID="UC-owner" and a connection attempt waiting for approval
- **AND** the token endpoint returns valid tokens and the channel request answers the channel id "UC-other", or no channel
- **WHEN** the page requests the status
- **THEN** the status answers "not_owner"
- **AND** a revocation request is sent to "https://oauth2.googleapis.com/revoke"
- **AND** no connection is stored
- **AND** "/settings/youtube-music" shows "This YouTube account isn't yours. Nothing was connected." and the "Connect YouTube Music" button

### Requirement: An unverifiable account connects nothing
When the channel request answers with an error or does not answer within 10 seconds, the system SHALL store nothing, try to revoke the tokens it received, answer the status "failed" and show "Couldn't check your YouTube account. Try again." with the "Connect YouTube Music" button.

#### Scenario: The channel cannot be checked
- **GIVEN** a connection attempt whose code the owner approved
- **WHEN** the channel request answers 503 or does not answer within 10 seconds
- **THEN** the status answers "failed" and a revocation request is sent
- **AND** no connection is stored
- **AND** "/settings/youtube-music" shows "Couldn't check your YouTube account. Try again." and the "Connect YouTube Music" button

### Requirement: Denying access connects nothing
When Google answers the token endpoint with "access_denied", the system SHALL end the attempt, store nothing, answer the status "denied" and show "You denied access to YouTube Music." with the "Connect YouTube Music" button.

#### Scenario: The owner denies access
- **GIVEN** a connection attempt waiting for approval
- **WHEN** Google answers the token endpoint with "access_denied"
- **THEN** the status answers "denied"
- **AND** "/settings/youtube-music" shows "You denied access to YouTube Music." and the "Connect YouTube Music" button
- **AND** no connection is stored

### Requirement: An expired code ends the attempt
When Google answers "expired_token", or the attempt's expires_in seconds pass without approval, the system SHALL end the attempt, answer the status "expired" and show "The code expired. Start again." with the "Connect YouTube Music" button. It SHALL not call the token endpoint with that device code again.

#### Scenario: The code expires
- **GIVEN** a connection attempt started with expires_in 1800
- **WHEN** Google answers "expired_token", or the page requests the status 1800 seconds after the start
- **THEN** the status answers "expired"
- **AND** "/settings/youtube-music" shows "The code expired. Start again." and the "Connect YouTube Music" button
- **AND** no later status request calls the token endpoint with that device code

### Requirement: Any other token refusal ends the attempt
When Google answers the token endpoint with the error "invalid_client", the system SHALL end the attempt, answer the status "failed" and show "YouTube Music is not configured correctly." Any other refusal SHALL end the attempt, answer "failed" and show "Couldn't connect YouTube Music. Try again." Neither SHALL store a connection.

#### Scenario: Google rejects the client while waiting
- **GIVEN** a connection attempt waiting for approval
- **WHEN** Google answers the token endpoint with status 401 and the error "invalid_client"
- **THEN** the status answers "failed"
- **AND** "/settings/youtube-music" shows "YouTube Music is not configured correctly." and the "Connect YouTube Music" button

#### Scenario: Google refuses for another reason
- **GIVEN** a connection attempt waiting for approval
- **WHEN** Google answers the token endpoint with status 400 and the error "invalid_request"
- **THEN** the status answers "failed"
- **AND** "/settings/youtube-music" shows "Couldn't connect YouTube Music. Try again."
- **AND** no connection is stored

### Requirement: A new attempt replaces the previous one
Starting the connection again SHALL request a new device code, show only the new user code and discard the previous device code, so no later token request carries it and it can never store a connection.

#### Scenario: Starting again
- **GIVEN** a connection attempt waiting with the device code "dev-123"
- **WHEN** the owner presses "Connect YouTube Music" again, in the same tab or another, and Google returns the device code "dev-456" and the user code "WXYZ-1234"
- **THEN** the page shows "WXYZ-1234" and not the previous user code
- **AND** no later request to the token endpoint carries "dev-123"

### Requirement: Concurrent status requests connect once
Status requests for the same attempt SHALL call Google one at a time, so two requests arriving together send one token request and store at most one connection.

#### Scenario: Two status requests at once
- **GIVEN** a connection attempt whose code the owner approved, with the interval passed
- **WHEN** two status requests arrive at the same time
- **THEN** one request is sent to the token endpoint and one connection is stored
- **AND** both status requests answer "connected" without error

### Requirement: Status without a waiting attempt
When no attempt is waiting, "GET /youtube-music/connect/status" SHALL answer "connected" if YouTube Music is connected and "idle" otherwise, without sending anything to Google.

#### Scenario: No attempt in progress
- **GIVEN** the owner signed in, no connection attempt and YouTube Music not connected
- **WHEN** the owner requests "GET /youtube-music/connect/status"
- **THEN** the status answers "idle"
- **AND** no request is sent to Google

### Requirement: YouTube Music tokens are encrypted at rest
The stored access and refresh tokens SHALL be encrypted in the database and SHALL be readable in plain text only through the application.

#### Scenario: Tokens are stored encrypted
- **GIVEN** YouTube Music connected with the access token "ya29.access-123" and the refresh token "1//refresh-456"
- **WHEN** the connection's columns are read straight from the database
- **THEN** no value contains "ya29.access-123" or "1//refresh-456"
- **AND** reading the connection through the application returns "ya29.access-123" and "1//refresh-456"

### Requirement: YouTube Music tokens stay off the pages
No page prop, shared Inertia prop or status response SHALL contain the access token, the refresh token or the device code.

#### Scenario: The settings page holds no tokens
- **GIVEN** YouTube Music connected with the access token "ya29.access-123" and the refresh token "1//refresh-456"
- **WHEN** the owner opens "/settings/youtube-music" and requests "GET /youtube-music/connect/status"
- **THEN** neither the page's Inertia props, including the shared props, nor the status response contain "ya29.access-123" or "1//refresh-456"

### Requirement: There is at most one YouTube Music connection
The system SHALL keep at most one YouTube Music connection. Connecting again SHALL replace its tokens, scopes, expiry and channel name in place, set its status to connected and give it a new connection identifier. An attempt that fails SHALL leave the existing connection unchanged.

#### Scenario: Reconnecting replaces the connection
- **GIVEN** a connection of "UC-owner" with the status "reconnect_required", the refresh token "1//refresh-456" and a connection identifier
- **WHEN** the owner connects again and Google returns the refresh token "1//refresh-777"
- **THEN** there is exactly one connection, for "UC-owner", with the status "connected"
- **AND** reading it returns the refresh token "1//refresh-777"
- **AND** its connection identifier differs from the previous one

#### Scenario: A failed attempt leaves the connection alone
- **GIVEN** a connection of "UC-owner" with the status "reconnect_required"
- **WHEN** the owner starts a new attempt and denies access, or the code expires
- **THEN** the connection is unchanged and still has the status "reconnect_required"

### Requirement: The YouTube Music settings page shows the connection state
"/settings/youtube-music" SHALL show "Connect YouTube Music" when not connected. When connected, it SHALL show "YouTube Music connected", the channel name and "Disconnect YouTube Music", and no "Connect YouTube Music". With the status "reconnect_required", it SHALL respond 200 and show "Your YouTube Music access expired or was revoked. Please reconnect YouTube Music." with "Connect YouTube Music".

#### Scenario: Not connected
- **GIVEN** the owner signed in, YouTube Music configured and not connected
- **WHEN** the owner opens "/settings/youtube-music"
- **THEN** the response status is 200
- **AND** the page shows the "Connect YouTube Music" button and no "Disconnect YouTube Music" button

#### Scenario: Connected
- **GIVEN** YouTube Music connected with the channel name "Alice Music"
- **WHEN** the owner opens "/settings/youtube-music"
- **THEN** the page shows "YouTube Music connected", "Alice Music" and the "Disconnect YouTube Music" button
- **AND** the page shows no "Connect YouTube Music" button

#### Scenario: Access expired or revoked
- **GIVEN** a connection with the status "reconnect_required"
- **WHEN** the owner opens "/settings/youtube-music"
- **THEN** the response status is 200
- **AND** the page shows "Your YouTube Music access expired or was revoked. Please reconnect YouTube Music." and the "Connect YouTube Music" button

### Requirement: The owner can disconnect YouTube Music
"DELETE /youtube-music/connection" SHALL try to revoke the stored token at Google's revocation endpoint, then delete the connection and its tokens even when the revocation fails, and redirect to "/settings/youtube-music". That page SHALL show "YouTube Music disconnected." with "Connect YouTube Music" and a mention of "myaccount.google.com/permissions". Without a connection the request SHALL succeed and send nothing to Google.

#### Scenario: Disconnecting YouTube Music
- **GIVEN** YouTube Music connected
- **WHEN** the owner presses "Disconnect YouTube Music" ("DELETE /youtube-music/connection")
- **THEN** a revocation request is sent to "https://oauth2.googleapis.com/revoke"
- **AND** no connection is stored
- **AND** the response redirects to "/settings/youtube-music", which shows "YouTube Music disconnected.", the "Connect YouTube Music" button and a mention of "myaccount.google.com/permissions"

#### Scenario: Disconnecting while Google does not answer
- **GIVEN** YouTube Music connected and the revocation endpoint answering 503 or not answering within 10 seconds
- **WHEN** the owner presses "Disconnect YouTube Music"
- **THEN** no connection is stored and the page shows "YouTube Music disconnected."

#### Scenario: Disconnecting without a connection
- **GIVEN** YouTube Music not connected
- **WHEN** a DELETE request is sent to "/youtube-music/connection"
- **THEN** the response redirects to "/settings/youtube-music", which responds 200
- **AND** no request is sent to Google

### Requirement: Pages share the YouTube Music connection status
Every Inertia page SHALL receive the shared prop "youtubeMusic.status" ("not_connected", "connected" or "reconnect_required") and "youtubeMusic.connected", which is true only when the status is "connected".

#### Scenario: Shared status for each connection state
- **GIVEN** the owner signed in
- **WHEN** the owner opens "/dashboard" with no connection, then with a connected connection, then with a connection in "reconnect_required"
- **THEN** "youtubeMusic.status" is "not_connected", "connected" and "reconnect_required" in turn
- **AND** "youtubeMusic.connected" is false, true and false in turn

### Requirement: YouTube Music secrets are not logged
The system SHALL NOT write the client secret, the device code, the access token, the refresh token or a Google response body to the logs, including when a request to Google fails.

#### Scenario: Failures log no secret
- **GIVEN** the client secret "secret-xyz", the device code "dev-123", the access token "ya29.access-123" and the refresh token "1//refresh-456"
- **AND** Google answering with a body that includes "upstream-body-detail"
- **WHEN** the device code request answers 503, the token endpoint answers 503 while waiting, or the revocation does not answer within 10 seconds
- **THEN** no log entry contains "secret-xyz", "dev-123", "ya29.access-123", "1//refresh-456" or "upstream-body-detail"

### Requirement: The YouTube Music settings page links to the Spotify settings
"/settings/youtube-music" SHALL show the settings link bar with "Spotify" and "YouTube Music", with "YouTube Music" marked as the current page, and "Spotify" SHALL lead to "/settings/spotify".

#### Scenario: Going back to the Spotify settings
- **GIVEN** the owner on "/settings/youtube-music"
- **WHEN** the owner presses "Spotify" in the settings link bar
- **THEN** "/settings/spotify" opens

### Requirement: The YouTube Music settings page uses the Aero look
"/settings/youtube-music" SHALL be built from the Aero components over the Aero background, with no element carrying a "data-slot" attribute. In every state, every enabled control SHALL be reachable with Tab and show a focus indicator of at least 2 px, and at a 375 px wide viewport the page SHALL fit without horizontal scrolling and give every control a touch area of at least 44x44 px.

#### Scenario: The page is Aero in every state
- **GIVEN** the owner signed in, and YouTube Music not connected, waiting for approval, connected and in "reconnect_required" in turn
- **WHEN** the owner opens "/settings/youtube-music" in a browser
- **THEN** the page shows an element with data-aero="background" and an element with data-aero="glass-panel"
- **AND** no element has a "data-slot" attribute

#### Scenario: The page shows keyboard focus
- **GIVEN** the owner on "/settings/youtube-music", in each state
- **WHEN** the owner moves through every enabled control with Tab
- **THEN** every enabled control is reached and shows an outline or ring of at least 2 px that it does not have unfocused

#### Scenario: The page fits a 375 px phone
- **GIVEN** a 375x667 px viewport and the owner on "/settings/youtube-music", in each state
- **WHEN** the page is shown
- **THEN** document.documentElement.scrollWidth is at most 375
- **AND** every control lies within the viewport width with a touch area of at least 44x44 px

### Requirement: The settings page shows a configuration error
When the connection carries the configuration-error mark that a refused renewal leaves, "/settings/youtube-music" SHALL show "YouTube Music is not configured correctly." next to the connection state, without sending anything to Google. Without the mark it SHALL not show that text.

#### Scenario: A renewal was refused for the client
- **GIVEN** YouTube Music connected with the channel name "Alice Music" and the configuration-error mark
- **WHEN** the owner opens "/settings/youtube-music"
- **THEN** the page shows "YouTube Music connected", "Alice Music" and "YouTube Music is not configured correctly."
- **AND** no request is sent to Google

#### Scenario: No configuration error
- **GIVEN** YouTube Music connected without the configuration-error mark
- **WHEN** the owner opens "/settings/youtube-music"
- **THEN** the page does not show "YouTube Music is not configured correctly."
