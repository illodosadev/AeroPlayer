# Spec Delta

## ADDED Requirements

### Requirement: The owner opens a playlist or Liked Songs from the library
Each playlist on the library page SHALL link to its playlist page, "/library/playlists/spotify/{id}", and the "Liked Songs" entry to "/library/liked". A visitor without a session SHALL be sent to the login page from either, without anything being sent to Spotify. Both pages SHALL be built from the Aero components only and follow the Aero focus and phone rules.

#### Scenario: Opening a playlist from the library
- **GIVEN** the owner on "/library" with the playlist "Road Trip"
- **WHEN** the owner presses "Road Trip"
- **THEN** the playlist page of "Road Trip" opens with status 200
- **AND** no element on the page has a "data-slot" attribute

#### Scenario: Opening Liked Songs from the library
- **GIVEN** the owner on "/library" with 12 saved songs
- **WHEN** the owner presses "Liked Songs"
- **THEN** the Liked Songs page opens with status 200

#### Scenario: A visitor without a session
- **GIVEN** a visitor without a session
- **WHEN** the visitor requests "/library/liked" or "/library/playlists/spotify/road"
- **THEN** each request is redirected to "/login"
- **AND** no request is sent to Spotify

#### Scenario: The pages are usable with the keyboard and on a phone
- **GIVEN** the owner on a playlist page and on the Liked Songs page, each with songs and "Load more"
- **WHEN** the owner walks each page with Tab, and later views it 375 px wide
- **THEN** every enabled control shows a 2 px focus outline
- **AND** at 375 px each page has no horizontal scroll and every control keeps a 44x44 px touch area inside the viewport

### Requirement: A playlist page lists its songs 50 at a time
For a playlist the owner owns or collaborates on, the playlist page SHALL show its title, cover and Spotify's song count, and its first 50 songs in Spotify's order, each with its title, artists, album, duration as m:ss and the label "Spotify". Each song SHALL have exactly source, id, uri, title, artists, album, duration_ms, image, playable, position and local, where position is the song's index in the Spotify playlist.

#### Scenario: Seeing the songs of an own playlist
- **GIVEN** Spotify connected and the own playlist "Road Trip" with "Song A" (3:45), "Song B" (4:02) and "Song C" (2:58)
- **WHEN** the owner opens "Road Trip"
- **THEN** the page shows the 3 songs in that order, each with its title, artists, album and duration
- **AND** it shows "3 songs"
- **AND** each song in the page props has exactly the keys source, id, uri, title, artists, album, duration_ms, image, playable, position and local

#### Scenario: A playlist with many songs
- **GIVEN** Spotify connected and an own playlist with 230 songs
- **WHEN** the owner opens the playlist
- **THEN** the page shows the first 50 songs, "230 songs" and "Load more"
- **WHEN** the owner presses "Load more"
- **THEN** the page shows 100 songs
- **AND** Spotify received requests for the playlist's items with limit 50 and offsets 0 and 50

#### Scenario: Opening a playlist directly
- **GIVEN** Spotify connected and the own playlist "Road Trip"
- **WHEN** the owner opens "/library/playlists/spotify/{id}" of "Road Trip" without visiting "/library"
- **THEN** the page shows "Road Trip" and its songs

### Requirement: A playlist the owner only follows is playable but not listed
For a playlist the owner neither owns nor collaborates on, the playlist page SHALL show "Spotify doesn't let this app list the songs of playlists you don't own." and an enabled "Play" button, and SHALL NOT ask Spotify for its songs.

#### Scenario: Opening a followed playlist
- **GIVEN** Spotify connected and the playlist "Indie Picks" by "Alice", which the owner only follows
- **WHEN** the owner opens "Indie Picks"
- **THEN** the response has status 200 and the page shows "Spotify doesn't let this app list the songs of playlists you don't own." and an enabled "Play"
- **AND** no request for the playlist's items is sent

### Requirement: Some playlist entries cannot play or are hidden
A local file SHALL show dimmed with "Local file, can't play here", with local true and playable false. A song Spotify marks as not playable SHALL show dimmed with "Not available". Pressing either SHALL play nothing. Entries without a song, such as deleted songs and podcast episodes, SHALL be hidden, and the song count SHALL stay Spotify's total.

#### Scenario: A local file in a playlist
- **GIVEN** the own playlist "Road Trip" with a local file "My Demo"
- **WHEN** the owner opens "Road Trip"
- **THEN** "My Demo" shows dimmed with "Local file, can't play here", and its props have local true and playable false
- **WHEN** the owner presses "My Demo"
- **THEN** no start request reaches Spotify

#### Scenario: A song that is not available
- **GIVEN** the own playlist "Road Trip" with the song "Gone Song", which Spotify marks as not playable
- **WHEN** the owner opens "Road Trip"
- **THEN** "Gone Song" shows dimmed with "Not available"
- **WHEN** the owner presses "Gone Song"
- **THEN** no start request reaches Spotify

#### Scenario: A song deleted from Spotify
- **GIVEN** the own playlist "Road Trip", for which Spotify answers a total of 3 and 3 entries, one of them without a song
- **WHEN** the owner opens "Road Trip"
- **THEN** the page shows the 2 songs and "3 songs"
- **AND** it shows no error

### Requirement: Empty and unavailable playlists say so
An own or collaborative playlist without songs SHALL show "This playlist is empty." and a disabled "Play". When Spotify answers 404 or 403 for the playlist or its songs, the page SHALL answer with status 200, show "This playlist is no longer available." and a link back to "/library".

#### Scenario: An empty playlist
- **GIVEN** the own playlist "New Ideas" without songs
- **WHEN** the owner opens "New Ideas"
- **THEN** the page shows "This playlist is empty." and "Play" is disabled

#### Scenario Outline: A playlist that is no longer available
- **GIVEN** Spotify answering <status> for the playlist
- **WHEN** the owner opens its playlist page
- **THEN** the response has status 200 and the page shows "This playlist is no longer available." and a link to "/library"

##### Examples
| status |
| 404 |
| 403 |

### Requirement: The Liked Songs page lists saved songs 50 at a time
The Liked Songs page SHALL show Spotify's saved-song count and the first 50 saved songs, newest first, each with its title, artists, album, duration and the label "Spotify". Each song SHALL have exactly source, id, uri, title, artists, album, duration_ms, image, playable and added_at.

#### Scenario: Seeing the saved songs
- **GIVEN** Spotify connected with 3 saved songs, newest first
- **WHEN** the owner opens "Liked Songs"
- **THEN** the page shows the 3 songs in that order, each with its title, artists, album, duration and the label "Spotify", and "3 songs"
- **AND** each song in the page props has exactly the keys source, id, uri, title, artists, album, duration_ms, image, playable and added_at

#### Scenario: Thousands of saved songs
- **GIVEN** Spotify connected with 3200 saved songs
- **WHEN** the owner opens "Liked Songs"
- **THEN** the page shows the first 50 songs, "3,200 songs" and "Load more"
- **AND** Spotify received one request for saved songs, with limit 50 and offset 0
- **WHEN** the owner presses "Load more"
- **THEN** the page shows 100 songs and Spotify received a request with offset 50

### Requirement: Liked Songs says when it is empty or lacks permission
Without saved songs, the Liked Songs page SHALL show "No liked songs yet.". Without the saved-songs permission it SHALL show "Reconnect Spotify to grant access to your library." and a "Reconnect Spotify" button, and send nothing to Spotify.

#### Scenario: No saved songs
- **GIVEN** Spotify connected with playlists and no saved songs
- **WHEN** the owner opens "Liked Songs"
- **THEN** the page shows "No liked songs yet."

#### Scenario: The saved-songs permission is missing on Liked Songs
- **GIVEN** Spotify connected and the granted scopes without "user-library-read"
- **WHEN** the owner opens "Liked Songs"
- **THEN** the page shows "Reconnect Spotify to grant access to your library." and the button "Reconnect Spotify"
- **AND** no request for saved songs is sent

### Requirement: "Load more" adds the next songs
"Load more" SHALL add the next 50 songs below the shown ones, asking with an opaque cursor from the app, and SHALL disappear when there are no more. While loading it SHALL be disabled. When loading fails, the shown songs SHALL stay, the page SHALL show "Couldn't load more songs. Try again in a moment." and "Load more" SHALL stay enabled. A cursor the app did not make for that list SHALL be rejected with status 422 without asking Spotify.

#### Scenario: A double click loads one page
- **GIVEN** a playlist page with 50 songs and more in Spotify
- **WHEN** the owner presses "Load more" twice in a row
- **THEN** Spotify receives one request with offset 50 and the page shows 100 songs

#### Scenario: Loading more fails
- **GIVEN** the Liked Songs page with 50 songs, and Spotify answering the next request with 503
- **WHEN** the owner presses "Load more"
- **THEN** the page still shows the 50 songs, "Couldn't load more songs. Try again in a moment." and an enabled "Load more"

#### Scenario: A foreign cursor is rejected
- **GIVEN** the owner signed in and Spotify connected
- **WHEN** the next Liked Songs page is requested with a cursor made for a playlist, or with "not-a-cursor"
- **THEN** the response has status 422
- **AND** no request is sent to Spotify

### Requirement: Playlist songs play in the playlist's context
"Play" on a playlist page SHALL play the playlist as a Spotify context from position 0. Pressing a playable song SHALL play the playlist as a context from that song's position in Spotify, counting hidden entries. "Play" on a playlist the owner only follows SHALL play it as a context from its start, without a song chosen by the app, since its songs cannot be listed.

#### Scenario: Playing a playlist from the start
- **GIVEN** the own playlist "Road Trip" with uri "spotify:playlist:road", open, with the player ready
- **WHEN** the owner presses "Play"
- **THEN** Spotify receives a start request with context_uri "spotify:playlist:road" and offset position 0

#### Scenario: Playing a playlist from a song
- **GIVEN** "Road Trip" open with "Song A" (position 0), "Song B" (position 1) and "Song C" (position 2)
- **WHEN** the owner presses "Song B"
- **THEN** Spotify receives a start request with context_uri "spotify:playlist:road" and offset position 1

#### Scenario: The position counts hidden entries
- **GIVEN** "Road Trip" with an entry without a song at position 0 and "Song B" at position 1
- **WHEN** the owner presses "Song B", the first song shown
- **THEN** Spotify receives a start request with context_uri "spotify:playlist:road" and offset position 1

#### Scenario: Playing a followed playlist
- **GIVEN** the followed playlist "Indie Picks" with uri "spotify:playlist:indie", open, with the player ready
- **WHEN** the owner presses "Play"
- **THEN** Spotify receives a start request with context_uri "spotify:playlist:indie" and offset position 0, the playback default

### Requirement: Saved songs play as their page
Pressing a playable saved song SHALL play the playable songs of its page of 50, in order, starting from that song. Songs that cannot play SHALL NOT be sent.

#### Scenario: Playing a saved song
- **GIVEN** "Liked Songs" open with "Song X", "Song Y" and "Song Z", all playable, and the player ready
- **WHEN** the owner presses "Song Y"
- **THEN** Spotify receives a start request with the uris of "Song X", "Song Y" and "Song Z", in that order, and offset position 1

#### Scenario: Songs that cannot play are not sent
- **GIVEN** "Liked Songs" open with "Song X", "Gone Song" (not playable) and "Song Z"
- **WHEN** the owner presses "Song Z"
- **THEN** Spotify receives a start request with the uris of "Song X" and "Song Z" and offset position 1

### Requirement: The playlist and Liked Songs pages show connection and failure states
Both pages SHALL answer with status 200 and never an error page. Without a connection they SHALL show "Connect Spotify to see your library." and "Connect Spotify"; with revoked access, the reconnect notice and "Reconnect Spotify"; while Spotify's rate limit lasts, "Spotify is busy right now. Try again in a moment."; on any other failure, "Couldn't load your Spotify library." and "Try again". When playback cannot start, they SHALL show "Playback couldn't start. Try again in a moment.".

#### Scenario Outline: A page cannot be loaded
- **GIVEN** <situation>
- **WHEN** the owner opens <page>
- **THEN** the response has status 200 and the page shows <message>

##### Examples
| situation | page | message |
| Spotify not connected | "Liked Songs" | "Connect Spotify to see your library." |
| the token refresh rejected with "invalid_grant" | a playlist page | "Your Spotify access expired or was revoked. Please reconnect Spotify." |
| Spotify's rate-limit pause in force | "Liked Songs" | "Spotify is busy right now. Try again in a moment." |
| Spotify answering 503 for the playlist | a playlist page | "Couldn't load your Spotify library." |

#### Scenario: Playback cannot start
- **GIVEN** a playlist page with the player ready, and Spotify answering the start request with 503
- **WHEN** the owner presses "Play"
- **THEN** the page shows "Playback couldn't start. Try again in a moment." and keeps its songs
