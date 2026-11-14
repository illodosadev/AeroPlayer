# Spec Delta

## Purpose

Lets the owner connect their own Spotify Premium account to AeroPlayer, keeps its tokens on the server and lets the owner see and end that connection from the Spotify settings page.

## ADDED Requirements

### Requirement: Connecting starts Spotify authorization
When the signed-in owner starts the connection, the system SHALL redirect to Spotify's authorization page with the authorization code flow, the configured client and redirect URI, the scopes the Spotify features need, a forced consent dialog and a random single-use state of at least 40 characters kept in the session.

#### Scenario: Starting the connection redirects to Spotify with the scopes and a state
- **GIVEN** the owner signed in, Spotify configured and not connected
- **WHEN** the owner requests "GET /spotify/connect"
- **THEN** the response redirects to "https://accounts.spotify.com/authorize"
- **AND** the URL has response_type=code, the configured client_id, the configured redirect_uri and show_dialog=true
- **AND** the scope parameter holds exactly "streaming", "user-read-email", "user-read-private", "user-modify-playback-state", "user-read-playback-state", "user-read-currently-playing", "playlist-read-private", "playlist-read-collaborative" and "user-library-read"
- **AND** the state parameter is at least 40 characters long and equals the state kept in the session

### Requirement: The callback requires a matching single-use state
The callback SHALL exchange the authorization code only when its state equals the state kept in the session, and SHALL remove that state on first use. A missing, different or already used state SHALL be rejected with "Could not connect Spotify. Please try again." without any request to Spotify.

#### Scenario: Tampered state
- **GIVEN** the owner signed in and the state "state-abc" in the session
- **WHEN** the callback "/spotify/callback?code=code-123&state=state-other" arrives
- **THEN** the response redirects to "/settings/spotify", which shows "Could not connect Spotify. Please try again."
- **AND** no request is sent to Spotify
- **AND** no connection is stored

#### Scenario: Missing state
- **GIVEN** the owner signed in and the state "state-abc" in the session
- **WHEN** the callback "/spotify/callback?code=code-123" arrives without a state
- **THEN** the response redirects to "/settings/spotify", which shows "Could not connect Spotify. Please try again."
- **AND** no request is sent to Spotify

#### Scenario: Repeated callback
- **GIVEN** a callback with code "code-123" and state "state-abc" already completed successfully
- **WHEN** the same callback "/spotify/callback?code=code-123&state=state-abc" arrives again
- **THEN** the response redirects to "/settings/spotify", which shows "Could not connect Spotify. Please try again."
- **AND** no new request is sent to Spotify
- **AND** the existing connection does not change

### Requirement: The owner's account connects
When the code exchange succeeds and Spotify reports the configured owner account, the callback SHALL store the connection and redirect to "/settings/spotify", which SHALL show "Spotify connected" with the account's display name. The connection SHALL record the account id, the granted scopes, the access token expiry and the authorization date.

#### Scenario: Connecting the owner's account
- **GIVEN** SPOTIFY_OWNER_ID="owner-123", the owner signed in, Spotify not connected and the state "state-abc" in the session
- **AND** Spotify (faked) returns for the code "code-123" the access token "BQ-access-123", the refresh token "AQ-refresh-456", the granted scopes and expires_in 3600
- **AND** Spotify (faked) answers "GET https://api.spotify.com/v1/me" with id "owner-123" and display_name "Alice", without a "product" field
- **WHEN** the callback "/spotify/callback?code=code-123&state=state-abc" arrives
- **THEN** the response redirects to "/settings/spotify", which shows "Spotify connected" and "Alice"
- **AND** there is exactly one connection, for the account "owner-123", with the granted scopes and an access token expiry 3600 seconds from now
- **AND** the state is no longer in the session

### Requirement: Spotify tokens are encrypted at rest
The stored access and refresh tokens SHALL be encrypted in the database and SHALL be readable in plain text only through the application.

#### Scenario: Tokens are stored encrypted
- **GIVEN** Spotify connected with the access token "BQ-access-123" and the refresh token "AQ-refresh-456"
- **WHEN** the connection's columns are read straight from the database
- **THEN** no value contains "BQ-access-123" or "AQ-refresh-456"
- **AND** reading the connection through the application returns "BQ-access-123" and "AQ-refresh-456"

### Requirement: Spotify tokens stay off the pages
The Spotify refresh token SHALL never be sent to the browser. No page prop or shared Inertia prop SHALL contain the access token or the refresh token. Only an endpoint added by the playback feature MAY send the access token to the browser player.

#### Scenario: The settings page props hold no tokens
- **GIVEN** Spotify connected with the access token "BQ-access-123" and the refresh token "AQ-refresh-456"
- **WHEN** the owner opens "/settings/spotify"
- **THEN** the page's Inertia props, including the shared props, contain neither "BQ-access-123" nor "AQ-refresh-456"

### Requirement: There is at most one Spotify connection
The system SHALL keep at most one Spotify connection. Connecting the same account again SHALL replace its tokens, scopes, expiry and authorization date in place, set its status to connected and give it a new connection identifier.

#### Scenario: Reconnecting the same account
- **GIVEN** Spotify connected with the account "owner-123", the refresh token "AQ-refresh-456" and a connection identifier
- **WHEN** the owner completes the connection again with the account "owner-123" and Spotify returns the refresh token "AQ-new-777"
- **THEN** there is exactly one connection, for the account "owner-123"
- **AND** reading it through the application returns the refresh token "AQ-new-777"
- **AND** its connection identifier differs from the previous one

#### Scenario: Reconnecting clears the reconnect-required status
- **GIVEN** a connection of "owner-123" with the status "reconnect_required" and no tokens
- **WHEN** the owner completes the connection with the account "owner-123"
- **THEN** the connection has the status "connected" and the new tokens

### Requirement: Only the owner's Spotify account is accepted
When Spotify reports an account id other than the configured owner id, the callback SHALL store nothing, SHALL leave any existing connection unchanged and SHALL show "Only the owner's Spotify account can be connected."

#### Scenario: Another Spotify account is rejected
- **GIVEN** SPOTIFY_OWNER_ID="owner-123", the owner signed in and the state "state-abc" in the session
- **AND** the exchange of the code "code-123" returns valid tokens
- **AND** Spotify (faked) answers "GET https://api.spotify.com/v1/me" with id "other-999"
- **WHEN** the callback "/spotify/callback?code=code-123&state=state-abc" arrives
- **THEN** the response redirects to "/settings/spotify", which shows "Only the owner's Spotify account can be connected."
- **AND** no connection or token is stored

#### Scenario: Another account leaves the owner's connection untouched
- **GIVEN** SPOTIFY_OWNER_ID="owner-123", the owner signed in and Spotify connected with the account "owner-123" and the refresh token "AQ-refresh-456"
- **AND** the state "state-abc" in the session, a valid code exchange and "GET /v1/me" answering id "other-999"
- **WHEN** the callback "/spotify/callback?code=code-123&state=state-abc" arrives
- **THEN** the page shows "Only the owner's Spotify account can be connected."
- **AND** there is exactly one connection, for "owner-123", and reading it returns the refresh token "AQ-refresh-456"

### Requirement: A non-Premium account is rejected
When Spotify reports a "product" other than "premium" for the account, the callback SHALL store nothing and SHALL show "Spotify Premium is required." When Spotify does not report a "product", the connection SHALL go ahead.

#### Scenario: The owner's account without Premium
- **GIVEN** SPOTIFY_OWNER_ID="owner-123", the owner signed in and the state "state-abc" in the session
- **AND** the exchange of the code "code-123" returns valid tokens
- **AND** Spotify (faked) answers "GET https://api.spotify.com/v1/me" with id "owner-123" and product "free"
- **WHEN** the callback "/spotify/callback?code=code-123&state=state-abc" arrives
- **THEN** the response redirects to "/settings/spotify", which shows "Spotify Premium is required."
- **AND** no connection or token is stored

### Requirement: An account not allowed by the Spotify app is rejected
When Spotify answers the account lookup with status 403, the callback SHALL store nothing and SHALL show "This Spotify account is not allowed to use this app."

#### Scenario: Account missing from the Spotify app's user list
- **GIVEN** the owner signed in and the state "state-abc" in the session
- **AND** the exchange of the code "code-123" returns valid tokens
- **AND** Spotify (faked) answers "GET https://api.spotify.com/v1/me" with status 403
- **WHEN** the callback "/spotify/callback?code=code-123&state=state-abc" arrives
- **THEN** the response redirects to "/settings/spotify", which shows "This Spotify account is not allowed to use this app."
- **AND** no connection or token is stored

### Requirement: Cancelling on Spotify connects nothing
When the callback carries the matching state and reports that the owner denied access, the system SHALL show "Spotify connection was cancelled." without exchanging any code. A denial with a missing or different state SHALL be rejected as an invalid state.

#### Scenario: The owner cancels on Spotify's page
- **GIVEN** the owner signed in, Spotify not connected and the state "state-abc" in the session
- **WHEN** the callback "/spotify/callback?error=access_denied&state=state-abc" arrives
- **THEN** the response redirects to "/settings/spotify", which shows "Spotify connection was cancelled."
- **AND** no request is sent to Spotify's token endpoint
- **AND** Spotify stays not connected

#### Scenario: Cancellation with a wrong state
- **GIVEN** the owner signed in, Spotify not connected and the state "state-abc" in the session
- **WHEN** the callback "/spotify/callback?error=access_denied&state=state-other" arrives
- **THEN** the response redirects to "/settings/spotify", which shows "Could not connect Spotify. Please try again."
- **AND** no request is sent to Spotify

### Requirement: A rejected code exchange connects nothing
When Spotify rejects the code exchange (for example an expired or used code), the callback SHALL store nothing and SHALL show "Could not connect Spotify. Please try again."

#### Scenario: Expired or used code
- **GIVEN** the owner signed in and the state "state-abc" in the session
- **AND** Spotify (faked) answers the exchange of the code "code-old" with status 400 and error "invalid_grant"
- **WHEN** the callback "/spotify/callback?code=code-old&state=state-abc" arrives
- **THEN** the response redirects to "/settings/spotify", which shows "Could not connect Spotify. Please try again."
- **AND** no connection is stored

### Requirement: Spotify not responding during the connection
When Spotify answers the code exchange or the account lookup with a server error or a rate limit, or does not answer within 10 seconds, the callback SHALL store nothing and SHALL show "Spotify is not responding right now. Try again in a moment."

#### Scenario: The token endpoint answers 503
- **GIVEN** the owner signed in and the state "state-abc" in the session
- **AND** Spotify (faked) answers the exchange of the code "code-123" with status 503
- **WHEN** the callback "/spotify/callback?code=code-123&state=state-abc" arrives
- **THEN** the response redirects to "/settings/spotify", which shows "Spotify is not responding right now. Try again in a moment."
- **AND** no connection is stored

#### Scenario: The account lookup cannot be reached
- **GIVEN** the owner signed in, the state "state-abc" in the session and a valid code exchange
- **AND** the request "GET https://api.spotify.com/v1/me" fails with a connection error
- **WHEN** the callback "/spotify/callback?code=code-123&state=state-abc" arrives
- **THEN** the page shows "Spotify is not responding right now. Try again in a moment."
- **AND** no connection is stored

### Requirement: Spotify routes require a session
"/spotify/connect", "/spotify/callback", "/spotify/connection", "/settings" and "/settings/spotify" SHALL require the signed-in owner. Without a session they SHALL redirect to "/login" and send nothing to Spotify.

#### Scenario: Callback without a session
- **GIVEN** a visitor without a session
- **WHEN** the callback "/spotify/callback?code=code-123&state=state-abc" arrives
- **THEN** the response redirects to "/login"
- **AND** no request is sent to Spotify

### Requirement: Spotify must be configured to connect
When the client id, the client secret or the owner's Spotify id is not configured, the system SHALL treat Spotify as not configured. "GET /spotify/connect" SHALL then redirect to "/settings/spotify", which SHALL show "Spotify is not configured." and no "Connect Spotify" button, and nothing SHALL be sent to Spotify.

#### Scenario: A Spotify setting is missing
- **GIVEN** every Spotify setting configured except one of SPOTIFY_CLIENT_ID, SPOTIFY_CLIENT_SECRET or SPOTIFY_OWNER_ID, which is empty
- **WHEN** the owner requests "GET /spotify/connect"
- **THEN** the response redirects to "/settings/spotify", which shows "Spotify is not configured."
- **AND** the page shows no "Connect Spotify" button
- **AND** no request is sent to Spotify

### Requirement: The Spotify settings page shows the connection state
"/settings/spotify" SHALL show "Connect Spotify" when Spotify is not connected. When connected, it SHALL show "Spotify connected", the account name, "Reconnect before <date>" six months after the authorization date (the last day of that month when the same day does not exist), and "Disconnect Spotify".

#### Scenario: Not connected
- **GIVEN** the owner signed in, Spotify configured and not connected
- **WHEN** the owner opens "/settings/spotify"
- **THEN** the response status is 200
- **AND** the page shows the "Connect Spotify" button and no "Disconnect Spotify" button

#### Scenario: Connected with the reconnect date
- **GIVEN** Spotify connected as "Alice", authorized on 2026-11-11
- **WHEN** the owner opens "/settings/spotify"
- **THEN** the page shows "Spotify connected", "Alice" and "Reconnect before 2027-05-11"
- **AND** the page shows the "Disconnect Spotify" button

#### Scenario: Reconnect date at the end of a month
- **GIVEN** Spotify connected, authorized on 2026-08-31
- **WHEN** the owner opens "/settings/spotify"
- **THEN** the page shows "Reconnect before 2027-02-28"

### Requirement: The settings page asks to reconnect when needed
When the connection has the status "reconnect_required", "/settings/spotify" SHALL respond 200 and show "Your Spotify access expired or was revoked. Please reconnect Spotify." with "Connect Spotify". When the granted scopes lack any required scope, it SHALL show "Reconnect Spotify to grant the new permissions." with "Connect Spotify".

#### Scenario: Access expired or revoked
- **GIVEN** a connection of "owner-123" with the status "reconnect_required" and no tokens
- **WHEN** the owner opens "/settings/spotify"
- **THEN** the response status is 200
- **AND** the page shows "Your Spotify access expired or was revoked. Please reconnect Spotify." and the "Connect Spotify" button

#### Scenario: Scopes missing after the app asks for more permissions
- **GIVEN** Spotify connected with granted scopes that do not include "user-read-playback-state"
- **WHEN** the owner opens "/settings/spotify"
- **THEN** the response status is 200
- **AND** the page shows "Reconnect Spotify to grant the new permissions." and the "Connect Spotify" button

### Requirement: The owner can disconnect Spotify
"DELETE /spotify/connection" SHALL delete the stored connection and its tokens, then redirect to "/settings/spotify". That page SHALL show "Spotify disconnected." with the "Connect Spotify" button, and SHALL say that access can also be removed at spotify.com/account/apps. Without a connection the request SHALL still succeed.

#### Scenario: Disconnecting Spotify
- **GIVEN** Spotify connected
- **WHEN** the owner presses "Disconnect Spotify" (DELETE "/spotify/connection")
- **THEN** no connection is stored
- **AND** the response redirects to "/settings/spotify", which shows "Spotify disconnected.", the "Connect Spotify" button and a mention of "spotify.com/account/apps"

#### Scenario: Disconnecting without a connection
- **GIVEN** Spotify not connected
- **WHEN** a DELETE request is sent to "/spotify/connection"
- **THEN** the response redirects to "/settings/spotify"
- **AND** that page responds with status 200

### Requirement: The settings entry point leads to the Spotify settings
"GET /settings" SHALL redirect the signed-in owner to "/settings/spotify".

#### Scenario: Opening the settings
- **GIVEN** the owner signed in
- **WHEN** the owner requests "GET /settings"
- **THEN** the response redirects to "/settings/spotify"

### Requirement: Pages share the Spotify connection status
Every Inertia page SHALL receive the shared prop "spotify.status" ("not_connected", "connected" or "reconnect_required") and the shared prop "spotify.connected", which is true only when the status is "connected".

#### Scenario: Shared status for each connection state
- **GIVEN** the owner signed in
- **WHEN** the owner opens "/dashboard" with no connection, then with a connected connection, then with a connection in "reconnect_required"
- **THEN** "spotify.status" is "not_connected", "connected" and "reconnect_required" in turn
- **AND** "spotify.connected" is false, true and false in turn

### Requirement: Spotify secrets are not logged
The system SHALL NOT write the authorization code, the client secret, the access token or the refresh token to the logs, including when a connection fails.

#### Scenario: A failed code exchange logs no secret
- **GIVEN** the client secret "secret-xyz", the owner signed in and the state "state-abc" in the session
- **AND** Spotify (faked) answers the exchange of the code "code-123" with status 503
- **WHEN** the callback "/spotify/callback?code=code-123&state=state-abc" arrives
- **THEN** no log entry contains "code-123" or "secret-xyz"

### Requirement: The Spotify settings page uses the Aero look
"/settings/spotify" SHALL be built from the Aero components over the Aero background, and SHALL contain no element with a "data-slot" attribute (the starter kit's components). Every enabled control SHALL be reachable with Tab and show a focus indicator of at least 2 px. At a 375 px wide viewport the page SHALL fit without horizontal scrolling and give every interactive control a touch area of at least 44x44 px.

#### Scenario: The settings page is Aero
- **GIVEN** the owner signed in
- **WHEN** the owner opens "/settings/spotify" in a browser
- **THEN** the page shows an element with data-aero="background" and an element with data-aero="glass-panel"
- **AND** no element has a "data-slot" attribute

#### Scenario: The settings page shows keyboard focus
- **GIVEN** the owner signed in and Spotify connected
- **WHEN** the owner opens "/settings/spotify" and moves through every enabled control with Tab
- **THEN** every enabled control is reached
- **AND** each focused control shows an outline or ring of at least 2 px that it does not have unfocused

#### Scenario: The settings page fits a 375 px phone
- **GIVEN** a 375x667 px viewport and the owner signed in
- **WHEN** the owner opens "/settings/spotify"
- **THEN** document.documentElement.scrollWidth is at most 375
- **AND** every control lies within the 375 px viewport width
- **AND** the touch area of every interactive control is at least 44x44 px
