# Spec Delta

## ADDED Requirements

### Requirement: The owner has a search page
"GET /search" SHALL show the signed-in owner the search page, with a field labelled "Search songs, albums and artists" and the text "Type to search your music.". The dashboard sidebar SHALL link to it as "Search". A visitor without a session SHALL be sent to the login page without anything being sent to Spotify. The page SHALL be built from the Aero components only.

#### Scenario: Opening the search page
- **GIVEN** the owner signed in and Spotify connected
- **WHEN** the owner presses "Search" in the dashboard sidebar
- **THEN** the search page opens with the field "Search songs, albums and artists" and the text "Type to search your music."
- **AND** no element on the page has a "data-slot" attribute

#### Scenario: A visitor without a session
- **GIVEN** a visitor without a session
- **WHEN** the visitor requests "GET /search"
- **THEN** the visitor is redirected to "/login"
- **AND** no request is sent to Spotify

#### Scenario: The page is usable with the keyboard and on a phone
- **GIVEN** the owner on the search page with results for "Daft Punk"
- **WHEN** the owner walks the page with Tab, and later views it 375 px wide
- **THEN** every enabled control shows a 2 px focus outline
- **AND** at 375 px the page has no horizontal scroll and every control keeps a 44x44 px touch area inside the viewport

### Requirement: A search starts when the owner stops typing
The page SHALL search 300 ms after the last keystroke, with the query trimmed. Enter SHALL search at once and cancel the pending wait. An empty or blank field SHALL send nothing and show "Type to search your music.". The field SHALL accept at most 200 characters.

#### Scenario: Typing quickly sends one search
- **GIVEN** the owner on the search page with Spotify connected
- **WHEN** the owner types "Daft Punk" one key at a time, less than 300 ms apart
- **THEN** no search is sent while typing
- **AND** 300 ms after the last key exactly one search is sent, with q "Daft Punk"

#### Scenario: Enter searches at once
- **GIVEN** the owner on the search page with Spotify connected
- **WHEN** the owner types "Daft Punk" and presses Enter before 300 ms pass
- **THEN** the search for "Daft Punk" is sent at once
- **AND** no second search for "Daft Punk" is sent when the 300 ms pass

#### Scenario: Surrounding spaces and an empty field
- **GIVEN** the owner on the search page with Spotify connected
- **WHEN** the owner types "   Daft Punk   "
- **THEN** the search sent carries q "Daft Punk"
- **WHEN** the owner then empties the field, or leaves only spaces in it
- **THEN** no search is sent, the results disappear and the page shows "Type to search your music."

#### Scenario: The field takes at most 200 characters
- **GIVEN** the owner on the search page
- **WHEN** the owner types 205 characters
- **THEN** the field holds the first 200

### Requirement: The newest search wins
A search SHALL cancel the one still in progress, and an answer that does not belong to the current query SHALL never replace what the page shows.

#### Scenario: A late answer does not replace newer results
- **GIVEN** the owner searched "Daft" and its answer is slower than the next one
- **WHEN** the owner then searches "Daft Punk" and its answer arrives before the one for "Daft"
- **THEN** the page shows the results for "Daft Punk"
- **AND** after the answer for "Daft" arrives, the page still shows the results for "Daft Punk"

### Requirement: Results show in songs, albums and artists sections
The page SHALL show the sections "Songs", "Albums" and "Artists", each in the order Spotify returned. A section without results SHALL be hidden; when all three are empty, the page SHALL show 'No results for "<query>"' and no section.

#### Scenario: Searching Spotify from the page
- **GIVEN** the owner signed in and Spotify connected
- **AND** Spotify answers "Daft Punk" with 3 tracks, 2 albums and 1 artist
- **WHEN** the owner searches "Daft Punk"
- **THEN** the page shows "Songs" with 3 rows, "Albums" with 2 and "Artists" with 1, in Spotify's order

#### Scenario: No results
- **GIVEN** Spotify answers "zzqxjkwvvy" with no tracks, albums or artists
- **WHEN** the owner searches "zzqxjkwvvy"
- **THEN** the page shows 'No results for "zzqxjkwvvy"'
- **AND** it shows none of the sections "Songs", "Albums" and "Artists"

#### Scenario: An empty section is hidden
- **GIVEN** Spotify answers with 3 tracks, 2 albums and no artists
- **WHEN** the results are shown
- **THEN** the page shows "Songs" and "Albums" and no "Artists" section

#### Scenario: An expired token does not stop the search
- **GIVEN** Spotify connected with an access token that expired a minute ago, and the refresh succeeding
- **WHEN** the owner searches "Daft Punk"
- **THEN** the page shows the results and no "Reconnect Spotify"

### Requirement: Result rows show their details
A song row SHALL show its cover, title, artists separated by commas, album, duration as m:ss and the label "Spotify". An album row SHALL show its cover, title, artists and year; an artist row its picture and name. An item without an image SHALL show the generic Aero cover.

#### Scenario: A song row shows its details and its service
- **GIVEN** the result "One More Time" by "Daft Punk", from "Discovery", lasting 320000 ms, with a cover
- **WHEN** the results are shown
- **THEN** its row shows "One More Time", "Daft Punk", "Discovery", "5:20", the album cover and the label "Spotify"

#### Scenario: An item without an image
- **GIVEN** a result artist without images
- **WHEN** the results are shown
- **THEN** its row shows the generic Aero cover and no broken image

### Requirement: Each section loads more results
A section with a next page SHALL show "Load more", which SHALL add the section's next 10 results below the shown ones. While it loads, "Load more" SHALL be disabled. A result already shown in the section SHALL NOT be added again. "Load more" SHALL disappear when the section has no next page. When loading fails, the shown rows SHALL stay, the section SHALL show "Spotify search isn't available right now. Try again in a moment." and "Load more" SHALL stay visible and enabled.

#### Scenario: Loading more songs
- **GIVEN** the search "Daft Punk" with 25 tracks in Spotify and the first 10 shown
- **WHEN** the owner presses "Load more" in "Songs"
- **THEN** Spotify receives a search with type "track", limit 10 and offset 10
- **AND** "Songs" shows 20 rows
- **WHEN** the owner presses "Load more" in "Songs" again
- **THEN** "Songs" shows 25 rows and no "Load more"

#### Scenario: A double click sends one request
- **GIVEN** "Songs" with 10 rows and more results in Spotify
- **WHEN** the owner presses "Load more" in "Songs" twice in a row
- **THEN** Spotify receives one search with offset 10
- **AND** "Songs" shows 20 rows, none repeated

#### Scenario: A song repeated across pages shows once
- **GIVEN** "Songs" with 10 rows, one of them the track "4uLU6hMCjMI75M1A2tKUQC"
- **WHEN** the owner presses "Load more" and the next page brings "4uLU6hMCjMI75M1A2tKUQC" again
- **THEN** that song shows once in "Songs"

#### Scenario: Loading more fails
- **GIVEN** "Songs" with 10 rows and more results in Spotify
- **AND** Spotify answers the next search with 503
- **WHEN** the owner presses "Load more" in "Songs"
- **THEN** "Songs" still shows the 10 rows and the message "Spotify search isn't available right now. Try again in a moment."
- **AND** "Load more" is visible and enabled
- **WHEN** Spotify answers again and the owner presses "Load more"
- **THEN** "Songs" shows 20 rows and no longer shows the message

#### Scenario: No more results to load
- **GIVEN** Spotify answers "Daft Punk" with 2 albums in total
- **WHEN** the results are shown
- **THEN** "Albums" shows no "Load more"

### Requirement: Pressing a song plays it
Pressing a playable song SHALL play only that song on the browser's player. A song Spotify marks as not playable SHALL show dimmed with "Not available", and pressing it SHALL play nothing. When playback cannot start, the page SHALL show "Playback couldn't start. Try again in a moment.".

#### Scenario: Playing a song from the results
- **GIVEN** the owner's browser player ready, and results with the song "spotify:track:0DiWol3AO6WpXZgp0goxAV"
- **WHEN** the owner presses that song
- **THEN** Spotify receives exactly one start request for the browser's device with uris ["spotify:track:0DiWol3AO6WpXZgp0goxAV"] and no context

#### Scenario: A song that is not available
- **GIVEN** results with the song "6DCZcSspjsKoFjzjrWoCdn", which Spotify marks as not playable
- **WHEN** the results are shown
- **THEN** its row is dimmed and shows "Not available"
- **WHEN** the owner presses it
- **THEN** no start request reaches Spotify

#### Scenario: Playback cannot start
- **GIVEN** the owner's browser player ready, and Spotify answering the start request with 503
- **WHEN** the owner presses a playable song
- **THEN** the page shows "Playback couldn't start. Try again in a moment."
- **AND** the results stay on the page

### Requirement: The page asks for a Spotify connection when it lacks one
Without a Spotify connection, the page SHALL show "Connect Spotify to search its catalog." and a "Connect Spotify" button that starts the connection, and SHALL send no search. When Spotify access was revoked, it SHALL show "Spotify access was revoked." and a "Reconnect Spotify" button. Neither SHALL show an error page.

#### Scenario: Searching without Spotify connected
- **GIVEN** the owner signed in and Spotify not connected
- **WHEN** the owner opens the search page
- **THEN** the response has status 200
- **AND** the page shows "Connect Spotify to search its catalog." and the button "Connect Spotify"
- **AND** no request is sent to Spotify

#### Scenario: Spotify access was revoked
- **GIVEN** Spotify connected and the token refresh rejected with "invalid_grant"
- **WHEN** the owner searches "Daft Punk"
- **THEN** the page shows "Spotify access was revoked." and the button "Reconnect Spotify"
- **AND** it shows no error page

### Requirement: Search failures show a message instead of an error page
When Spotify cannot answer a search (rate limit, error or no answer), the page SHALL clear the previous results and show "Spotify search isn't available right now. Try again in a moment."; the owner's next search is the retry. When the session has ended, the page SHALL send the owner to the login page.

#### Scenario Outline: Spotify cannot answer
- **GIVEN** the page showing results for "Daft Punk"
- **AND** Spotify answers the next search with <answer>
- **WHEN** the owner searches "Justice"
- **THEN** the page shows "Spotify search isn't available right now. Try again in a moment." and no results
- **AND** it shows no error page

##### Examples
| answer |
| 429 with "Retry-After: 30" |
| 503 |
| no answer before the timeout |

#### Scenario: The rate-limit pause is respected
- **GIVEN** Spotify answered a search with 429 and "Retry-After: 30"
- **WHEN** the owner searches "Justice" 10 seconds later
- **THEN** no request is sent to Spotify and the page shows "Spotify search isn't available right now. Try again in a moment."
- **WHEN** the owner searches "Justice" 31 seconds after the 429
- **THEN** the request is sent to Spotify and the page shows its results

#### Scenario: The session ended
- **GIVEN** the owner on the search page whose session has ended
- **WHEN** the owner searches "Daft Punk"
- **THEN** the owner is sent to the login page
