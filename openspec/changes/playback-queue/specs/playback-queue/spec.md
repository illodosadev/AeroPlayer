# Spec Delta

## Purpose

Keeps the owner's playback queue on the server, so it is the same on every device, and lets the owner add songs to it, see it in a panel of the player bar, reorder it and remove songs, with concurrent edits from two devices detected instead of silently lost.

## ADDED Requirements

### Requirement: The queue is read in full
"GET /queue" SHALL answer 200 with the owner's queue: "version", "current_item_id", "shuffle", "repeat", "mode", "context", "has_more" and "items". "items" SHALL hold every song of the queue in order, played ones included, each with exactly item_id, source, id, uri, title, artists, album, duration_ms, image and origin. Until shuffle, repeat and Spotify contexts exist, "shuffle" SHALL be false, "repeat" "off", "mode" "queue", "context" null and "has_more" false.

#### Scenario: Reading a stored queue
- **GIVEN** the owner signed in and a stored queue with "Song A" (current), "Song B" and "Song C"
- **WHEN** the browser requests "GET /queue"
- **THEN** the response has status 200 with "version", "current_item_id", "shuffle" = false, "repeat" = "off", "mode" = "queue", "context" = null, "has_more" = false and "items"
- **AND** "items" holds the 3 songs in that order, each with exactly the keys item_id, source, id, uri, title, artists, album, duration_ms, image and origin
- **AND** "current_item_id" is the item_id of "Song A"

#### Scenario: Played songs stay in the queue
- **GIVEN** a stored queue with "Song C" (played), "Song A" (current) and "Song D"
- **WHEN** the browser requests "GET /queue"
- **THEN** "items" holds "Song C", "Song A" and "Song D", in that order, and "current_item_id" is the item_id of "Song A"

### Requirement: A queue that was never used reads as empty
For an owner who never used the queue, "GET /queue" SHALL answer 200 with an empty "items", "current_item_id" null and "version" 0.

#### Scenario: No queue yet
- **GIVEN** the owner signed in, who never used the queue
- **WHEN** the browser requests "GET /queue"
- **THEN** the response has status 200 with "items" empty, "current_item_id" null and "version" = 0

### Requirement: The queue API needs a session
Every queue endpoint SHALL answer a JSON request without a session with status 401 and "error" = "unauthenticated", change nothing and send nothing to Spotify. Every answer of the queue API SHALL carry "Cache-Control: no-store".

#### Scenario Outline: A visitor without a session
- **GIVEN** a visitor without a session
- **WHEN** they send "<request>" as a JSON request
- **THEN** the response has status 401 and the JSON has "error" = "unauthenticated"
- **AND** no queue is changed and no request is sent to Spotify

##### Examples
| request |
| GET /queue |
| PUT /queue |
| PATCH /queue |
| POST /queue/items |
| PATCH /queue/items/{item} |
| DELETE /queue/items/{item} |

#### Scenario: Queue answers are not cached
- **GIVEN** the owner signed in
- **WHEN** the browser requests "GET /queue"
- **THEN** the "Cache-Control" header contains "no-store"

### Requirement: Queue songs keep the common format and their origin
A song sent to the queue SHALL use the app's common song format. The queue SHALL store its source, id, uri, title, artists, album, duration_ms and image, give it a new item_id, and return its "origin" exactly as sent, or null when none was sent. The system SHALL NOT interpret the origin.

#### Scenario: The origin is kept as it is
- **GIVEN** the owner signed in
- **WHEN** the browser sends "PUT /queue" with a song whose origin is {"type": "own_playlist", "playlist_id": 12, "item_id": 345}
- **THEN** "GET /queue" returns that song with the same origin, unchanged

#### Scenario: The same song twice
- **GIVEN** a queue with "Song A" as its only song
- **WHEN** the browser adds "Song A" again
- **THEN** "items" holds "Song A" twice, with two different item_ids

### Requirement: Only playable Spotify songs enter the queue
A write that adds songs SHALL answer 422 with "error" = "invalid_track" and change nothing when any song lacks a title, is not playable, has a source other than "spotify", has a uri that is not a Spotify track uri, has an id that does not match its uri, or has an origin that is not null or an object with a text "type" of at most 1 KB.

#### Scenario Outline: An invalid song
- **GIVEN** a queue with "version" = 4
- **WHEN** the browser sends "POST /queue/items" with version 4 and a song with <problem>
- **THEN** the response has status 422 with "error" = "invalid_track"
- **AND** the queue still has "version" = 4

##### Examples
| problem |
| uri "spotify:album:2noRn2Aes5aoNVsU6iWThc" |
| uri "spotify:track:abc" |
| source "deezer" |
| playable false |
| no title |
| origin "own_playlist" (a text, not an object) |

### Requirement: Adding songs appends them
"POST /queue/items" with the client's version and one or more songs SHALL add them at the end of the queue in the order sent, with origin null unless given, raise the version by one and answer 200 with the full queue. When the queue has no current song, the first added song SHALL become the current one.

#### Scenario: Adding a song saves it and raises the version
- **GIVEN** a queue with "version" = 4 and 2 songs
- **WHEN** the browser sends "POST /queue/items" with version 4 and the song "spotify:track:0DiWol3AO6WpXZgp0goxAV" in the common format
- **THEN** the response has status 200 with "version" = 5 and 3 songs
- **AND** the new song is the last of "items", with "origin" = null

#### Scenario: Adding to an empty queue makes the song current
- **GIVEN** an empty queue
- **WHEN** the browser sends "POST /queue/items" with version 0 and "Song X"
- **THEN** the response has "version" = 1 and "current_item_id" = the item_id of "Song X"

### Requirement: Replacing the queue always wins
"PUT /queue" with a list of songs and the index of the current one, 0 by default, SHALL replace every song of the queue, regardless of the version the client holds. It SHALL raise the version by one and answer 200 with the full queue. It SHALL answer 422 "tracks_required" for an empty list, "current_index_out_of_range" for an index outside the list, and "queue_full" for more than 500 songs.

#### Scenario: Replacing with an old version
- **GIVEN** a queue in "version" = 9 and a device that loaded it in "version" = 7
- **WHEN** that device sends "PUT /queue" with 3 songs and current_index 1
- **THEN** the response has status 200 with "version" = 10, the 3 songs and "current_item_id" of the second one

#### Scenario Outline: A replacement that cannot apply
- **GIVEN** a queue with "version" = 6
- **WHEN** the browser sends "PUT /queue" with <input>
- **THEN** the response has status 422 with "error" = "<code>"
- **AND** the queue still has "version" = 6 and no change

##### Examples
| input | code |
| "tracks" empty | tracks_required |
| 3 songs and current_index 3 | current_index_out_of_range |
| 501 songs | queue_full |

### Requirement: The current song can be moved
"PATCH /queue" with the client's version and a "current_item_id" from the queue SHALL make that song the current one without reordering anything. Songs before it count as played and songs after it as pending. It SHALL raise the version by one and answer 200 with the full queue. An item that is not in the queue SHALL answer 404 "queue_item_not_found".

#### Scenario: Moving forward marks the skipped songs as played
- **GIVEN** a queue in "version" = 3 with "Song A" (current), "Song B", "Song C" and "Song D"
- **WHEN** the browser sends "PATCH /queue" with version 3 and the current_item_id of "Song C"
- **THEN** the response has "version" = 4, "current_item_id" of "Song C" and "items" in the same order

#### Scenario: Moving to an unknown song
- **GIVEN** a queue in "version" = 3
- **WHEN** the browser sends "PATCH /queue" with version 3 and a current_item_id that is not in the queue
- **THEN** the response has status 404 with "error" = "queue_item_not_found"
- **AND** the queue still has "version" = 3

### Requirement: A pending song can be moved
"PATCH /queue/items/{item}" with the client's version and a position SHALL move that pending song to the position, counted from 0 among the pending songs, keeping the order of the others. It SHALL raise the version by one and answer 200 with the full queue. It SHALL answer 404 "queue_item_not_found" for an unknown item, 422 "item_not_pending" for the current or a played song, and 422 "position_out_of_range" for a position outside the pending songs.

#### Scenario: Moving a song to the front of the pending songs
- **GIVEN** a queue in "version" = 2 with "Song A" (current) and "Song B", "Song C" and "Song D" pending
- **WHEN** the browser sends "PATCH /queue/items/{item}" for "Song D" with version 2 and position 0
- **THEN** the response has "version" = 3 and "items" in the order "Song A", "Song D", "Song B", "Song C"

#### Scenario Outline: A move that cannot apply
- **GIVEN** a queue with "version" = 6, "Song A" played, "Song B" current and "Song C", "Song D" and "Song E" pending
- **WHEN** the browser sends "PATCH /queue/items/{item}" with version 6 for <item> and position <position>
- **THEN** the response has status <status> with "error" = "<code>"
- **AND** the queue still has "version" = 6 and no change

##### Examples
| item | position | status | code |
| an item that does not exist | 0 | 404 | queue_item_not_found |
| "Song B", the current one | 0 | 422 | item_not_pending |
| "Song A", already played | 0 | 422 | item_not_pending |
| "Song E" | 3 | 422 | position_out_of_range |

### Requirement: A song can be removed
"DELETE /queue/items/{item}" with the client's version SHALL remove that song, raise the version by one and answer 200 with the full queue. Removing the current song SHALL make the next pending song the current one, or leave no current song when none is pending. An unknown item SHALL answer 404 "queue_item_not_found".

#### Scenario: Removing a pending song
- **GIVEN** a queue in "version" = 5 with "Song A" (current), "Song B" and "Song C"
- **WHEN** the browser sends "DELETE /queue/items/{item}" for "Song B" with version 5
- **THEN** the response has "version" = 6 and "items" "Song A", "Song C"

#### Scenario: Removing the current song
- **GIVEN** a queue with "Song A" (current), "Song B" and "Song C"
- **WHEN** the browser removes "Song A"
- **THEN** "current_item_id" is the item_id of "Song B"

#### Scenario: Removing the last song, which is the current one
- **GIVEN** a queue with "Song A" (current) and no pending songs
- **WHEN** the browser removes "Song A"
- **THEN** "current_item_id" is null

#### Scenario: Removing an unknown song
- **GIVEN** a queue in "version" = 5
- **WHEN** the browser sends "DELETE /queue/items/{item}" with version 5 for an item that does not exist
- **THEN** the response has status 404 with "error" = "queue_item_not_found"
- **AND** the queue still has "version" = 5

### Requirement: A stale version is rejected with the current queue
Every queue write except "PUT /queue" SHALL carry the version the client holds. When it differs from the stored version, the write SHALL change nothing and answer 409 with "error" = "queue_version_conflict" and the current queue in "queue". Two writes with the same version SHALL never both succeed.

#### Scenario: A change from another device
- **GIVEN** the laptop and the phone with the queue in "version" = 7
- **AND** the phone removes a song, so the queue is in "version" = 8
- **WHEN** the laptop sends "POST /queue/items" with version 7
- **THEN** the response has status 409 with "error" = "queue_version_conflict" and "queue" holding the queue in "version" = 8
- **AND** the song the laptop sent is not in the queue

#### Scenario: Two writes with the same version
- **GIVEN** a queue in "version" = 3
- **WHEN** two "POST /queue/items" requests with version 3 are sent one after the other
- **THEN** the first answers 200 with "version" = 4 and the second answers 409 "queue_version_conflict"

### Requirement: A malformed queue request is rejected
A queue write whose body is malformed in a way no other code covers, such as a missing or non-integer version or position, or nothing to change, SHALL answer 422 with "error" = "invalid_request" and change nothing. A write that sends no songs to "POST /queue/items" SHALL answer 422 "tracks_required".

#### Scenario Outline: A malformed write
- **GIVEN** a queue with "version" = 4 and "Song A" current
- **WHEN** the browser sends <request>
- **THEN** the response has status 422 with "error" = "<code>"
- **AND** the queue still has "version" = 4

##### Examples
| request | code |
| "POST /queue/items" with a song and no version | invalid_request |
| "POST /queue/items" with version 4 and "tracks" empty | tracks_required |
| "PATCH /queue/items/{item}" with version "four" and position 0 | invalid_request |
| "PATCH /queue" with version 4 and no field to change | invalid_request |

### Requirement: The queue holds at most 500 songs
The queue SHALL hold at most 500 songs, counting played, current and pending ones. An addition that would go past it SHALL answer 422 "queue_full" and change nothing, and the page SHALL show "The queue is full (500 songs). Remove some songs first.".

#### Scenario: The queue is full
- **GIVEN** a queue with 500 songs
- **WHEN** the owner chooses "Add to queue" on another song
- **THEN** "POST /queue/items" answers 422 with "error" = "queue_full"
- **AND** the page shows "The queue is full (500 songs). Remove some songs first."
- **AND** the queue still has 500 songs

### Requirement: Song rows offer "Add to queue"
Every song row of the search results, the Liked Songs page and an own playlist page SHALL have an "Add to queue" button that adds the song at the end of the queue and shows "Added to queue". It SHALL be disabled for a song that cannot play. While its request is pending, pressing it SHALL do nothing and it SHALL keep the keyboard focus. Adding SHALL NOT interrupt what is playing.

#### Scenario Outline: Adding a song from a row
- **GIVEN** a song playing and a queue with 2 pending songs
- **WHEN** the owner chooses "Add to queue" on the song "Song X" of <origin>
- **THEN** "Song X" is the last song of "Next up"
- **AND** the page shows "Added to queue"
- **AND** no "pause()" is called and no start request is sent

##### Examples
| origin |
| the search results |
| the Liked Songs page |
| an own playlist page |

#### Scenario: Songs that cannot play cannot be added
- **GIVEN** search results with 3 songs, the second marked "Not available"
- **WHEN** their rows are shown
- **THEN** the "Add to queue" button of the second row is disabled
- **AND** those of the first and the third rows are enabled

#### Scenario: A double click adds the song once
- **GIVEN** the search results with "Song X"
- **WHEN** the owner presses "Add to queue" on "Song X" twice in a row
- **THEN** exactly one "POST /queue/items" is sent and "Song X" is in "Next up" once

#### Scenario: Adding does not play the row
- **GIVEN** the search results with "Song X" and the player ready
- **WHEN** the owner presses "Add to queue" on "Song X"
- **THEN** no start request reaches Spotify

#### Scenario: Adding with the keyboard keeps the focus
- **GIVEN** the search results with "Song X" and "POST /queue/items" answering after 1 second
- **WHEN** the owner focuses "Add to queue" on "Song X" and presses Enter
- **THEN** that button keeps the focus while the request is pending and after the answer
- **AND** pressing Enter again before the answer sends no second "POST /queue/items"

### Requirement: A tab sends its queue edits one after another
The browser SHALL send the queue edits of a tab one at a time, in the order the owner made them, each with the latest version the tab knows, which includes the answer to its previous edit. An edit made while another is pending SHALL wait for it instead of being dropped, and SHALL NOT be rejected as a conflict caused by the tab's own earlier edit.

#### Scenario: Adding two songs from two rows in a row
- **GIVEN** the search results with "Song X" and "Song Y" and a queue in "version" = 2
- **WHEN** the owner presses "Add to queue" on "Song X" and, before the answer, on "Song Y"
- **THEN** two "POST /queue/items" requests are sent, the first with version 2 and the second with version 3
- **AND** "Next up" ends with "Song X", "Song Y"
- **AND** the page does not show "The queue changed on another device."

### Requirement: The player bar opens the queue panel
The player bar SHALL have a "Queue" button with aria-expanded that opens and closes a panel. The panel SHALL show the current song under "Now playing" and the pending songs under "Next up", in order, each with its title, artists, duration and service label, and SHALL NOT show played songs. Escape SHALL close the panel and return the focus to "Queue".

#### Scenario: Seeing the queue
- **GIVEN** a queue with "Song C" played, "Song A" current and 3 pending songs
- **WHEN** the owner presses "Queue" in the player bar
- **THEN** the panel shows "Song A" under "Now playing" and the 3 pending songs under "Next up", in order
- **AND** each row shows its title, artists, duration and the label "Spotify"
- **AND** "Song C" is not shown
- **AND** the "Queue" button has aria-expanded = "true"

#### Scenario: Closing the panel with Escape
- **GIVEN** the queue panel open
- **WHEN** the owner presses Escape
- **THEN** the panel is closed, "Queue" has aria-expanded = "false" and has the focus

#### Scenario: The panel on a phone
- **GIVEN** a 375 px wide viewport and the queue panel open with 3 pending songs
- **WHEN** the owner looks at it
- **THEN** the page has no horizontal scroll and the panel is within the viewport
- **AND** every button of the panel has a touch area of at least 44x44 px

### Requirement: An empty queue says so
The queue panel SHALL show "Your queue is empty." when the queue has no current and no pending song.

#### Scenario: Empty queue
- **GIVEN** an empty queue
- **WHEN** the owner opens the queue panel
- **THEN** it shows "Your queue is empty."

### Requirement: The owner reorders and removes pending songs
Each pending row of the panel SHALL have "Move up", "Move down" and "Remove" buttons, and SHALL be movable by dragging it with the mouse. "Move up" SHALL be disabled on the first pending song and "Move down" on the last. The current song SHALL have "Remove" only. Every change SHALL be saved on the server, so it survives a reload.

#### Scenario: Reordering and removing songs
- **GIVEN** a queue with the songs A, B and C pending
- **WHEN** the owner drags C to the top of "Next up" and removes B
- **THEN** "Next up" shows C, A
- **AND** after a reload "Next up" still shows C, A

#### Scenario: Reordering with the keyboard
- **GIVEN** a queue with the songs A, B and C pending
- **WHEN** the owner focuses C's "Move up" button and presses it twice
- **THEN** "Next up" shows C, A, B
- **AND** the focus stays on C's "Move up" button after each move, while it is enabled

#### Scenario: The ends of the list
- **GIVEN** a queue with the songs A, B and C pending
- **WHEN** the owner opens the queue panel
- **THEN** A's "Move up" and C's "Move down" are disabled

### Requirement: Editing the queue never touches playback
Adding, moving and removing songs SHALL NOT pause, resume or start playback, nor change what the player bar shows.

#### Scenario: Reordering while a song plays
- **GIVEN** "Track A" playing in the player bar and the songs B and C pending in the queue
- **WHEN** the owner moves C before B and removes B
- **THEN** "pause()" is not called and no start request is sent
- **AND** the bar still shows "Track A" with the "Pause" button

### Requirement: Failed queue edits leave the queue as the server has it
A queue edit SHALL change the panel only after the server accepts it. When the server answers 5xx or the network fails, the page SHALL show "Couldn't update the queue. Try again." and keep the queue as it was. When it answers 409, the page SHALL show the queue from the answer and "The queue changed on another device.", and SHALL NOT resend the edit.

#### Scenario: The server fails during an edit
- **GIVEN** a queue with 2 pending songs
- **AND** "POST /queue/items" answers 503, or the network is down
- **WHEN** the owner chooses "Add to queue" on "Song X"
- **THEN** the page shows "Couldn't update the queue. Try again."
- **AND** "Next up" still shows the 2 songs, without "Song X"

#### Scenario: A stale version from another device
- **GIVEN** the laptop with the queue in "version" = 7
- **AND** the phone removed a song, so the queue is in "version" = 8
- **WHEN** the owner chooses "Add to queue" on "Song X" on the laptop
- **THEN** the laptop shows "The queue changed on another device."
- **AND** its panel shows the queue in version 8, without "Song X"
- **AND** the laptop does not send "POST /queue/items" again

### Requirement: The queue works without Spotify connected
The queue panel and its edits SHALL work while Spotify is not connected, since the queue lives on the server. The player bar's own message about the missing connection SHALL stay.

#### Scenario: Spotify not connected
- **GIVEN** the owner signed in, Spotify not connected and a stored queue with 3 songs
- **WHEN** the owner opens the queue panel
- **THEN** it shows the 3 songs
- **AND** the bar shows "Connect Spotify to play your music." and the "Play" button is disabled
- **AND** the owner can remove and reorder songs

### Requirement: Every device sees the same queue
The browser SHALL read the queue when a signed-in page loads, whenever the tab becomes visible again, and every 30 seconds while the tab is visible. A hidden tab SHALL NOT read the queue.

#### Scenario: The same queue on another device
- **GIVEN** on the laptop a queue with "Song C" (played), "Song A" (current) and "Song D"
- **WHEN** the owner opens the player on the phone
- **THEN** the phone's queue panel shows "Song A" under "Now playing" and "Song D" under "Next up"

#### Scenario: Returning to the tab shows changes from another device
- **GIVEN** the player open on the laptop with the queue in "version" = 3 and the tab hidden
- **AND** the phone adds "Song E", so the queue is in "version" = 4
- **WHEN** the laptop's tab becomes visible again
- **THEN** the laptop requests "GET /queue"
- **AND** its queue panel shows "Song E" at the end

#### Scenario: A visible tab reads the queue every 30 seconds
- **GIVEN** the player open and visible on the laptop with the queue in "version" = 3
- **AND** the phone adds "Song E", so the queue is in "version" = 4
- **WHEN** 31 seconds pass with the laptop's tab visible
- **THEN** the laptop has requested "GET /queue" at least once and at most twice in that time
- **AND** its queue panel shows "Song E" at the end

#### Scenario: A hidden tab does not read the queue
- **GIVEN** the player open on the laptop with the tab hidden
- **WHEN** 90 seconds pass
- **THEN** the laptop does not request "GET /queue"
