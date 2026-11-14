# Spec Delta

## Purpose
Lets the owner search their connected music services for songs, albums and artists and get the results in one service-independent format, page by page, so the search page and later sources such as YouTube Music share one contract.

## ADDED Requirements

### Requirement: A search returns songs, albums and artists from Spotify
"GET /search/results" with a query and no section SHALL search Spotify with one request for tracks, albums and artists, 10 of each from offset 0, in the account's market, with the query trimmed and otherwise sent as typed. It SHALL answer the songs, albums and artists sections, each in Spotify's order.

#### Scenario: Searching Spotify
- **GIVEN** the owner signed in and Spotify connected
- **AND** Spotify answers "Daft Punk" with 3 tracks, 2 albums and 1 artist
- **WHEN** the owner requests "GET /search/results?q=Daft Punk" as a JSON request
- **THEN** exactly one "GET https://api.spotify.com/v1/search" is sent with q "Daft Punk", type "track,album,artist", limit 10, offset 0 and market "from_token"
- **AND** the response has 3 songs, 2 albums and 1 artist, in the order Spotify returned them

#### Scenario: Surrounding spaces do not count
- **GIVEN** the owner signed in and Spotify connected
- **WHEN** the owner searches "   Daft Punk   "
- **THEN** the request to Spotify carries q "Daft Punk"

#### Scenario: Special characters reach Spotify unchanged
- **GIVEN** the owner signed in and Spotify connected
- **WHEN** the owner searches 'AC/DC & "Back in Black" #1 Beyoncé'
- **THEN** the request to Spotify carries, once decoded, q exactly 'AC/DC & "Back in Black" #1 Beyoncé'
- **AND** the response has status 200

#### Scenario: No results
- **GIVEN** the owner signed in and Spotify connected
- **AND** Spotify answers "zzqxjkwvvy" with no tracks, albums or artists
- **WHEN** the owner searches "zzqxjkwvvy"
- **THEN** the response has status 200 and all three sections are empty, with a null next cursor

### Requirement: Results use the app's common format
Every result SHALL use the app's field names, never Spotify's. A song SHALL have exactly source, id, uri, title, artists (names), album (title), duration_ms, image (a URL or null) and playable. An album SHALL have source, id, uri, title, artists, year and image. An artist SHALL have source, id, uri, title and image. The source SHALL be "spotify".

#### Scenario: Songs have the common format
- **GIVEN** the owner signed in and Spotify connected
- **WHEN** the owner searches "Daft Punk"
- **THEN** every song has exactly the keys source, id, uri, title, artists, album, duration_ms, image and playable
- **AND** "source" is "spotify", "artists" is a list of names, "album" is the album's title and "image" is a URL or null
- **AND** no song has the Spotify keys name, images, href, external_urls or is_playable

#### Scenario: Albums have the common format
- **GIVEN** the owner signed in and Spotify connected
- **WHEN** the owner searches "Daft Punk"
- **THEN** every album has exactly the keys source, id, uri, title, artists, year and image
- **AND** no album has the Spotify keys name, images, href, release_date or total_tracks

#### Scenario: Artists have the common format
- **GIVEN** the owner signed in and Spotify connected
- **WHEN** the owner searches "Daft Punk"
- **THEN** every artist has exactly the keys source, id, uri, title and image
- **AND** no artist has the Spotify keys name, images, href, genres or followers

#### Scenario: A song with every detail
- **GIVEN** Spotify returns the track "One More Time" by "Daft Punk", from the album "Discovery" with a cover, lasting 320000 ms
- **WHEN** the owner searches for it
- **THEN** its song has title "One More Time", artists ["Daft Punk"], album "Discovery", duration_ms 320000 and the album cover's URL as image

#### Scenario: Missing images and playability
- **GIVEN** Spotify returns an artist without images, a track marked "is_playable" false and a track without "is_playable"
- **WHEN** the owner searches for them
- **THEN** the artist's image is null
- **AND** the first track's playable is false and the second track's playable is true

### Requirement: A query must be 1 to 200 characters
"GET /search/results" SHALL answer 422 without sending anything to Spotify when the trimmed query is empty or longer than 200 characters.

#### Scenario Outline: An invalid query
- **GIVEN** the owner signed in and Spotify connected
- **WHEN** the owner requests "GET /search/results" as a JSON request with <query>
- **THEN** the response has status 422
- **AND** no request is sent to Spotify

##### Examples
| query |
| no q |
| q "   " |
| q of 201 characters |

### Requirement: Sections page with an opaque cursor
Each section SHALL come with a next cursor: an opaque text from the app, or null when Spotify has no next page or the next offset would reach 1000. Requesting a section with its cursor SHALL search only that type, 10 results from the cursor's offset, and answer that section's items and next cursor. Responses SHALL NOT expose Spotify's offset or next.

#### Scenario: Paging through songs
- **GIVEN** the owner signed in and Spotify connected, with 25 tracks for "Daft Punk"
- **WHEN** the owner searches "Daft Punk"
- **THEN** the songs section has a non-empty next cursor and no "offset" or "next" key
- **WHEN** the owner requests the "songs" section with that cursor
- **THEN** "GET /v1/search" is sent with type "track", limit 10 and offset 10
- **AND** the response has the next 10 songs and a non-empty next cursor
- **WHEN** the owner requests the "songs" section with the new cursor and Spotify answers with next null
- **THEN** the response has the last 5 songs and a null next cursor

#### Scenario: No more results
- **GIVEN** Spotify answers "Daft Punk" with 2 albums in total and next null
- **WHEN** the owner searches "Daft Punk"
- **THEN** the albums section has a null next cursor

### Requirement: An invalid cursor is rejected
"GET /search/results" SHALL answer 422 without sending anything to Spotify when a section is unknown, when a cursor comes without a section, or when the cursor cannot be read, was altered, carries an offset above 1000, or belongs to another section or query.

#### Scenario Outline: An invalid section or cursor
- **GIVEN** the owner signed in and Spotify connected
- **WHEN** the owner requests "GET /search/results?q=Daft Punk" with <input>
- **THEN** the response has status 422
- **AND** no request is sent to Spotify

##### Examples
| input |
| section "playlists" |
| a valid songs cursor and no section |
| section "songs" and cursor "not-a-cursor" |
| section "songs" and a valid songs cursor with one character changed |
| section "songs" and a cursor made by the app with offset 1010 |
| section "albums" and a valid songs cursor |
| section "songs" and a valid songs cursor made for the query "Justice" |

### Requirement: Search failures answer in JSON
When a search cannot be answered, "GET /search/results" SHALL answer JSON with an "error" code and never an HTML page: 401 "unauthenticated" for a JSON request without a session, 409 "spotify_not_connected", 409 "spotify_reconnect_required", 429 "rate_limited" with "retry_after", and 502 "spotify_unavailable" for any other Spotify failure. An expired token SHALL be refreshed without failing the search.

#### Scenario Outline: The search cannot be answered
- **GIVEN** <situation>
- **WHEN** "GET /search/results?q=Daft Punk" is requested as a JSON request
- **THEN** the response has status <status> and the JSON has "error" = "<code>"<extra>
- **AND** the response is not an HTML error page

##### Examples
| situation | status | code | extra |
| a visitor without a session | 401 | unauthenticated | |
| the owner signed in and Spotify not connected | 409 | spotify_not_connected | |
| Spotify connected and the refresh rejected with "invalid_grant" | 409 | spotify_reconnect_required | |
| Spotify answering 429 with "Retry-After: 30" | 429 | rate_limited | and "retry_after" = 30 |
| Spotify answering 503 | 502 | spotify_unavailable | |
| Spotify not answering before the timeout | 502 | spotify_unavailable | |
| Spotify answering 403 for an account outside the app's users | 502 | spotify_unavailable | |

#### Scenario: The rate-limit pause covers the search
- **GIVEN** Spotify answered a search with 429 and "Retry-After: 30"
- **WHEN** the owner searches "Justice" 10 seconds later
- **THEN** no request is sent to Spotify and the response is 429 "rate_limited" with "retry_after" = 20
- **WHEN** the owner searches "Justice" 31 seconds after the 429
- **THEN** the request is sent to Spotify

#### Scenario: An expired token does not stop the search
- **GIVEN** Spotify connected with an access token that expired a minute ago, and the refresh succeeding
- **WHEN** the owner searches "Daft Punk"
- **THEN** the response has status 200 with the results
