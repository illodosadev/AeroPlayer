# music-library Specification

## Purpose
Lets the owner see and play the playlists and saved songs of their connected music services in one unified library, with one service-independent format, so later sources such as YouTube Music join the same view.

## Requirements

### Requirement: The owner has a library page
"GET /library" SHALL show the signed-in owner the library page, and the dashboard sidebar SHALL link to it as "Library". A visitor without a session SHALL be sent to the login page without anything being sent to Spotify. The page SHALL be built from the Aero components only.

#### Scenario: Opening the library
- **GIVEN** the owner signed in and Spotify connected
- **WHEN** the owner presses "Library" in the dashboard sidebar
- **THEN** the page "Library" opens with status 200
- **AND** no element on the page has a "data-slot" attribute

#### Scenario: A visitor without a session
- **GIVEN** a visitor without a session
- **WHEN** the visitor requests "GET /library"
- **THEN** the visitor is redirected to "/login"
- **AND** no request is sent to Spotify

#### Scenario: The page is usable with the keyboard and on a phone
- **GIVEN** the owner on the library page with playlists
- **WHEN** the owner walks the page with Tab, and later views it 375 px wide
- **THEN** every enabled control shows a 2 px focus outline
- **AND** at 375 px the page has no horizontal scroll and every control keeps a 44x44 px touch area inside the viewport

### Requirement: The library shows every playlist and the Liked Songs count
The page SHALL show one "Playlists" list, with no tab per service, holding every playlist of the owner's Spotify library in Spotify's order, and one "Liked Songs" entry with the number of saved songs. The system SHALL read the playlists 50 per request, up to 1000, and the saved-song count with one request for a single song.

#### Scenario: Seeing the library
- **GIVEN** Spotify connected, with the playlists "Road Trip", "Chill" and "Gym", each with a cover, and 12 saved songs
- **WHEN** the owner opens "/library"
- **THEN** the page shows the 3 playlists in that order, each with its title, its cover and the label "Spotify"
- **AND** it shows one "Playlists" list and a "Liked Songs" entry with "12 songs", and no tabs

#### Scenario: Many playlists
- **GIVEN** Spotify connected, with 120 playlists
- **WHEN** the owner opens "/library"
- **THEN** the page shows the 120 playlists
- **AND** Spotify received 3 requests for the owner's playlists, with limit 50 and offsets 0, 50 and 100
- **AND** 1 request for the saved songs, with limit 1

#### Scenario: Large saved-song counts are grouped
- **GIVEN** Spotify connected, with 3200 saved songs
- **WHEN** the owner opens "/library"
- **THEN** the "Liked Songs" entry shows "3,200 songs"

### Requirement: Playlists use the app's common format
Every playlist SHALL have exactly source, id, uri, title, image, owner, collaborative, can_list_tracks and total, and never a Spotify field name. The source SHALL be "spotify". The image SHALL be a URL or null; the owner the owner's display name or null; can_list_tracks SHALL be true when the owner of the app owns the playlist or it is collaborative; total SHALL be Spotify's song count, or null when Spotify does not give it.

#### Scenario: Playlist props have the common format
- **GIVEN** Spotify connected, with the playlists "Road Trip" and "Chill"
- **WHEN** the owner opens "/library"
- **THEN** each playlist in the page props has exactly the keys source, id, uri, title, image, owner, collaborative, can_list_tracks and total
- **AND** "source" is "spotify" in both

#### Scenario: A playlist without a cover
- **GIVEN** Spotify connected and the playlist "Untitled Mix" with no images
- **WHEN** the owner opens "/library"
- **THEN** "Untitled Mix" has a null image in the props
- **AND** the page shows it with the generic Aero cover

#### Scenario: A playlist the owner only follows
- **GIVEN** Spotify connected and the playlist "Indie Picks" owned by "Alice", not collaborative, without a song count
- **WHEN** the owner opens "/library"
- **THEN** "Indie Picks" shows "by Alice" and no number of songs
- **AND** its props have can_list_tracks false and total null

#### Scenario: A collaborative playlist
- **GIVEN** Spotify connected and the collaborative playlist "Office Jams" owned by another user
- **WHEN** the owner opens "/library"
- **THEN** "Office Jams" shows the mark "Collaborative"
- **AND** its props have can_list_tracks true

### Requirement: Empty libraries say so
These texts SHALL apply only when both permissions were granted and both the playlists and the saved-song count were read; a missing permission SHALL show its own message instead. When there are no playlists and no saved songs, the page SHALL show "Your library is empty." and neither the "Playlists" list nor the "Liked Songs" entry. When there are saved songs but no playlists, it SHALL show the "Liked Songs" entry and "No playlists yet." in the "Playlists" list.

#### Scenario: An empty library
- **GIVEN** Spotify connected, with no playlists and 0 saved songs
- **WHEN** the owner opens "/library"
- **THEN** the page shows "Your library is empty."
- **AND** it shows neither the "Playlists" list nor the "Liked Songs" entry

#### Scenario: Saved songs but no playlists
- **GIVEN** Spotify connected, with no playlists and 12 saved songs
- **WHEN** the owner opens "/library"
- **THEN** the page shows the "Liked Songs" entry with "12 songs" and "No playlists yet." in the "Playlists" list
- **AND** it does not show "Your library is empty."

#### Scenario: A missing permission is not an empty library
- **GIVEN** Spotify connected, the granted scopes without "playlist-read-private", and 0 saved songs
- **WHEN** the owner opens "/library"
- **THEN** the page shows "Reconnect Spotify to grant access to your library." and the button "Reconnect Spotify"
- **AND** it does not show "Your library is empty."

### Requirement: The library is cached for 10 minutes
The system SHALL keep the playlists and the saved-song count for 10 minutes, tied to the current Spotify connection, so a new connection never reads an earlier one's data. "Refresh" SHALL drop the kept library so the page reads Spotify again.

#### Scenario: The library is served from the cache
- **GIVEN** Spotify connected and "/library" opened less than 10 minutes ago
- **WHEN** the owner opens "/library" again
- **THEN** the page shows the same playlists
- **AND** no new request is sent to Spotify

#### Scenario: The cache expires after 10 minutes
- **GIVEN** Spotify connected and "/library" opened 11 minutes ago
- **WHEN** the owner opens "/library" again
- **THEN** a new request for the owner's playlists is sent

#### Scenario: Refreshing the library
- **GIVEN** Spotify connected and "/library" opened less than 10 minutes ago
- **AND** the owner created the playlist "Fresh" in Spotify since then
- **WHEN** the owner presses "Refresh"
- **THEN** a new request for the owner's playlists is sent
- **AND** "Fresh" shows in the list

#### Scenario: A new connection does not read the old cache
- **GIVEN** Spotify connected and "/library" opened less than 10 minutes ago
- **WHEN** the owner disconnects Spotify, connects the same account again and opens "/library"
- **THEN** a new request for the owner's playlists is sent

### Requirement: The library asks for a Spotify connection when it lacks one
Without a Spotify connection, the page SHALL show "Connect Spotify to see your library." and a "Connect Spotify" button, and send nothing to Spotify. When the access was revoked, it SHALL show "Your Spotify access expired or was revoked. Please reconnect Spotify." and a "Reconnect Spotify" button, and no Spotify playlist.

#### Scenario: Spotify not connected
- **GIVEN** the owner signed in and Spotify not connected
- **WHEN** the owner opens "/library"
- **THEN** the page shows "Connect Spotify to see your library." and the button "Connect Spotify"
- **AND** no request is sent to Spotify

#### Scenario: Disconnecting hides the cached library
- **GIVEN** Spotify connected and the library kept in the cache
- **WHEN** the owner disconnects Spotify and opens "/library"
- **THEN** the page shows "Connect Spotify to see your library." and no Spotify playlist

#### Scenario: Spotify access was revoked
- **GIVEN** Spotify connected and the token refresh rejected with "invalid_grant"
- **WHEN** the owner opens "/library"
- **THEN** the response has status 200 and the page shows "Your Spotify access expired or was revoked. Please reconnect Spotify." and the button "Reconnect Spotify"
- **AND** it shows neither "Connect Spotify to see your library." nor any playlist or the "Liked Songs" entry

### Requirement: The library asks for a missing permission
When the playlists permission is missing, the page SHALL show "Reconnect Spotify to grant access to your library." and a "Reconnect Spotify" button in place of the "Playlists" list, without asking Spotify for playlists. When the saved-songs permission is missing, the "Liked Songs" entry SHALL show that text and no number, without asking Spotify for saved songs.

#### Scenario: The saved-songs permission is missing
- **GIVEN** Spotify connected and the granted scopes without "user-library-read"
- **WHEN** the owner opens "/library"
- **THEN** the "Liked Songs" entry shows "Reconnect Spotify to grant access to your library." and no number
- **AND** no request for saved songs is sent

#### Scenario: The playlists permission is missing
- **GIVEN** Spotify connected and the granted scopes without "playlist-read-private"
- **WHEN** the owner opens "/library"
- **THEN** in place of the "Playlists" list the page shows "Reconnect Spotify to grant access to your library." and the button "Reconnect Spotify"
- **AND** no request for the owner's playlists is sent

### Requirement: Library failures show a message instead of an error page
When Spotify cannot answer, the page SHALL answer with status 200 and a message, never an error page, and SHALL contain neither Spotify's response body nor a token. While Spotify's rate limit lasts and no library is kept, it SHALL show "Spotify is busy right now. Try again in a moment.". On any other failure it SHALL show "Couldn't load your Spotify library." and a "Try again" button.

#### Scenario: Spotify fails
- **GIVEN** Spotify connected and Spotify answering the playlists request with 503
- **WHEN** the owner opens "/library"
- **THEN** the response has status 200 and the page shows "Couldn't load your Spotify library." and the button "Try again"
- **AND** the page contains neither Spotify's response body nor the access token

#### Scenario: Trying again
- **GIVEN** the page showing "Couldn't load your Spotify library." and Spotify answering again
- **WHEN** the owner presses "Try again"
- **THEN** the page shows the playlists

#### Scenario: Spotify is busy and nothing is kept
- **GIVEN** Spotify connected, no library kept, and Spotify's rate-limit pause in force
- **WHEN** the owner opens "/library"
- **THEN** the response has status 200 and the page shows "Spotify is busy right now. Try again in a moment."
- **AND** no request is sent to Spotify
