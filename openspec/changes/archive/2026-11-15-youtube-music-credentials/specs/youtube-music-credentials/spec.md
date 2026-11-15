# Spec Delta

## Purpose

Gives AeroPlayer's features a valid YouTube access token on the owner's behalf from the stored YouTube Music connection, renewing it before it expires and turning every Google problem into a typed failure instead of an error page.

## ADDED Requirements

### Requirement: Credentials need a usable connection
A request for the access token SHALL fail as "not connected" when there is no YouTube Music connection, and as "reconnect required" when the connection has the status "reconnect_required". In both cases the system SHALL send nothing to Google.

#### Scenario: No connection
- **GIVEN** YouTube Music not connected
- **WHEN** a feature of the app asks for the access token
- **THEN** the request fails as "not connected"
- **AND** no request is sent to Google

#### Scenario: Connection waiting for a reconnect
- **GIVEN** a connection with the status "reconnect_required"
- **WHEN** a feature of the app asks for the access token
- **THEN** the request fails as "reconnect required"
- **AND** no request is sent to Google

### Requirement: A fresh access token is returned as it is
When the stored access token is valid for 60 seconds or more, the system SHALL return it without sending anything to Google.

#### Scenario: Token valid for an hour
- **GIVEN** YouTube Music connected with the access token "ya29.access-123" expiring in 3600 seconds
- **WHEN** a feature of the app asks for the access token
- **THEN** it receives "ya29.access-123"
- **AND** no request is sent to Google

### Requirement: An expiring access token is renewed silently
When the stored access token expires in less than 60 seconds, the system SHALL send Google one renewal with the stored refresh token, return the new access token, and store it encrypted with its new expiry, without any reconnect notice. It SHALL store a refresh token Google rotates and keep the current one when Google sends none.

#### Scenario: Token expired a minute ago
- **GIVEN** YouTube Music connected with the refresh token "1//refresh-456" and an access token that expired 1 minute ago
- **AND** Google (faked) answers the renewal with the access token "ya29.new-789" and expires_in 3599
- **WHEN** a feature of the app asks for the access token
- **THEN** exactly one request is sent to "https://oauth2.googleapis.com/token", with grant_type "refresh_token", the refresh token "1//refresh-456", the client id and the client secret
- **AND** the feature receives "ya29.new-789"
- **AND** the stored access token is "ya29.new-789", not in plain text in the database, with an expiry 3599 seconds from now
- **AND** the stored refresh token is still "1//refresh-456"
- **AND** the connection has the status "connected" and "/settings/youtube-music" shows no reconnect notice

#### Scenario: Google rotates the refresh token
- **GIVEN** YouTube Music connected with an expired access token and the refresh token "1//refresh-456"
- **WHEN** Google answers the renewal with the access token "ya29.new-789" and the refresh token "1//refresh-777"
- **THEN** the stored refresh token is "1//refresh-777"

### Requirement: Parallel requests renew only once
While one request renews the access token, another request that also finds it expiring SHALL wait for that renewal and use its result instead of sending a second renewal.

#### Scenario: Two requests at once
- **GIVEN** YouTube Music connected with an expired access token
- **WHEN** two features ask for the access token at the same time
- **THEN** one renewal is sent to Google
- **AND** both features receive the same new access token

### Requirement: Revoked, expired or unreadable access asks to reconnect
When Google refuses the renewal with "invalid_grant", or when the stored tokens are missing or can no longer be decrypted, the request SHALL fail as "reconnect required", and the connection SHALL take the status "reconnect_required" with its tokens deleted. The settings page SHALL then ask the owner to reconnect.

#### Scenario: The authorization expired or was revoked
- **GIVEN** YouTube Music connected with an expired access token
- **AND** Google answers the renewal with status 400 and the error "invalid_grant"
- **WHEN** a feature of the app asks for the access token
- **THEN** the request fails as "reconnect required"
- **AND** the connection has the status "reconnect_required" and no tokens
- **AND** "/settings/youtube-music" responds 200 and shows "Your YouTube Music access expired or was revoked. Please reconnect YouTube Music." and the "Connect YouTube Music" button

#### Scenario: Missing tokens
- **GIVEN** a connection with the status "connected" and no stored tokens
- **WHEN** a feature of the app asks for the access token
- **THEN** the request fails as "reconnect required" and the connection has the status "reconnect_required"
- **AND** no request is sent to Google

#### Scenario: Tokens that no longer decrypt
- **GIVEN** YouTube Music connected with tokens encrypted under another application key
- **WHEN** a feature of the app asks for the access token
- **THEN** the request fails as "reconnect required" and the connection has the status "reconnect_required"
- **AND** no request is sent to Google

### Requirement: Google not responding leaves the connection untouched
When Google answers the renewal with a server error or a rate limit (429), does not answer within 10 seconds, or another renewal holds the lock for more than 10 seconds, the request SHALL fail as "unavailable", and the connection SHALL keep its status and its tokens.

#### Scenario: The renewal fails for a moment
- **GIVEN** YouTube Music connected with an expired access token "ya29.access-123"
- **WHEN** Google answers the renewal with 503 or 429, or does not answer within 10 seconds
- **THEN** the request fails as "unavailable"
- **AND** the connection still has the status "connected" and the access token "ya29.access-123"
- **AND** "/settings/youtube-music" shows "YouTube Music connected" without any notice

#### Scenario: Another renewal never ends
- **GIVEN** YouTube Music connected with an expired access token, and another renewal holding the lock
- **WHEN** a feature of the app asks for the access token and the lock is still held after 10 seconds
- **THEN** the request fails as "unavailable"
- **AND** no request is sent to Google

### Requirement: A refused client is a configuration error
When Google refuses the renewal with "invalid_client", or the Google client is not configured, the request SHALL fail as "configuration not valid". The connection SHALL keep the status "connected" and its tokens and SHALL be marked with a configuration error, since reconnecting would not fix it. A not configured client SHALL send nothing to Google.

#### Scenario: The client secret changed
- **GIVEN** YouTube Music connected with an expired access token
- **WHEN** Google answers the renewal with status 401 and the error "invalid_client"
- **THEN** the request fails as "configuration not valid"
- **AND** the connection still has the status "connected" and its tokens
- **AND** "/settings/youtube-music" shows "YouTube Music is not configured correctly."

#### Scenario: The client is no longer configured
- **GIVEN** YouTube Music connected with an expired access token, and YOUTUBE_MUSIC_CLIENT_SECRET empty
- **WHEN** a feature of the app asks for the access token
- **THEN** the request fails as "configuration not valid"
- **AND** no request is sent to Google

### Requirement: A good renewal clears the configuration error
A successful renewal SHALL remove the configuration-error mark from the connection.

#### Scenario: The secret is fixed
- **GIVEN** YouTube Music connected with an expired access token and the configuration-error mark from an earlier "invalid_client"
- **AND** the client secret in ".env" corrected
- **WHEN** a feature of the app asks for the access token and Google answers the renewal successfully
- **THEN** "/settings/youtube-music" no longer shows "YouTube Music is not configured correctly."

### Requirement: Renewals keep secrets out
The system SHALL NOT log the access token, the refresh token, the client secret or a Google response body while renewing, and its failures SHALL NOT contain any of them.

#### Scenario: A failed renewal logs no secret
- **GIVEN** the client secret "secret-xyz", the access token "ya29.access-123" and the refresh token "1//refresh-456", with the access token expired
- **AND** Google answering the renewal with 503 and a body that includes "upstream-body-detail"
- **WHEN** a feature of the app asks for the access token
- **THEN** no log entry and no failure message contains "secret-xyz", "ya29.access-123", "1//refresh-456" or "upstream-body-detail"
