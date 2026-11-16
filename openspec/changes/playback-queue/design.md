# Design

## Context

See proposal.md, "Why". The requirements are in `specs/playback-queue/spec.md`. T-13's research, its rules and its decisions D2 to D5 are in `backlog/tasks/T-13-cola-de-reproduccion.md`. That file also holds the scenarios of changes 2 and 3, which this design leaves for later.

What exists:

- **The single-owner app:**
  - every signed-in route sits in the `auth` group of `routes/web.php`;
  - controllers read the owner with `$request->user()`, as `DisconnectSpotifyController` does, and pass the id to commands.
- **The JSON endpoints of the player:**
  - `RespondsWithSpotifyErrors` sends `Cache-Control: no-store, private`;
  - `bootstrap/app.php` renders the JSON 401 `unauthenticated` and the JSON 419 `session_expired` for a list of paths.
- **The common song format** is `SongResult`: source, id, uri, title, artists, album, duration_ms, image and playable. Playlist rows add `position` and `local`, and Liked Songs rows add `added_at`.
- **`SongRow.vue`** is the `<button>` itself, with `data-part="song-row"`. A second button cannot go inside it.
- **`PlayerLayout.vue`:**
  - it is the persistent layout of every signed-in page, with `AeroPlayerBar` fixed at the bottom;
  - it publishes the bar's height in `--app-player-height`;
  - the bar has an `extra` slot for more `AeroButton`s, and its notice area belongs to the player's notices.
- **`useSpotifyPlayer`** keeps module-level state, one per tab, and reads the `XSRF-TOKEN` cookie for its JSON writes. A 401 or 419 sends the browser to `/login`.
- **Only `UserRepository` opens a transaction.** It does so with `DB::transaction` inside the repository.

## Goals / Non-Goals

**Goals:**

- The tables and the API already carry the fields of changes 2 and 3 (`shuffle`, `repeat`, `mode`, `context_*`, `continuation`, `original_position`), so those changes add behavior without breaking the API contract.
- No write ever leaves two queues with the same version.
- The client never shows a queue the server did not accept.

**Non-Goals:**

- Optimistic edits in the client. T-13 rejects them for user edits.
- Driving playback (change 2). The queue's "current" item is only a pointer here.

## Decisions

### D1. Two tables named after the playback queue

| Table | Columns |
|---|---|
| `playback_queues` | `id`, `user_id` (unique, foreign key with cascade on delete), `version` (unsigned, default 0), `current_item_id` (nullable, no foreign key), `shuffle` (bool, false), `repeat` (string, `off`), `mode` (string, `queue`), `context_uri` and `context_title` (nullable), `continuation` (JSON, nullable) and timestamps |
| `playback_queue_items` | `id`, `playback_queue_id` (foreign key with cascade on delete), `position` and `original_position` (unsigned), `source` (32), `song_id` (64), `uri` (255), `title` (500), `artists` (JSON), `album` (500, nullable), `duration_ms` (unsigned), `image` (2048, nullable), `origin` (JSON, nullable) and timestamps, indexed on (`playback_queue_id`, `position`) |

- **The names** are `PlaybackQueue` and `PlaybackQueueItem`, not T-13's `queues` and `queue_items`. Laravel already uses "queue" for jobs (`config/queue.php` and the `Queue` facade). The URLs keep `/queue`.
- **The song's id** is stored as `song_id`, because `id` is the primary key. The API exposes it as `id` and the primary key as `item_id`.
- **`current_item_id` has no foreign key.** Each table would point at the other. The repository keeps it valid, and every write that removes the current item sets it in the same transaction.
- **`original_position`** gets the same value as `position` whenever a song is added. Change 3 uses it to undo a shuffle.

*Alternative:* adding the columns of changes 2 and 3 only when they arrive. Rejected: `GET /queue` already promises those keys, and fixed defaults would be a second source of truth.

### D2. Dense positions and a pointer

- **Positions are 0 to n-1 in the queue's order.** Every write that adds, moves or removes songs renumbers them in the same transaction. A queue holds at most 500 rows, so renumbering is cheap.
- **Played, current and pending are derived** from `current_item_id`: items before the current position are played, items after it are pending. With no current item, nothing is played or pending. The panel then shows "Your queue is empty.", even if played rows remain. The next song added becomes the current one.
- **Moving a pending song** to position `p` places it at index `current + 1 + p` and keeps the other songs in order.
- **Removing the current song** makes the item right after it current, or sets null when it was the last item.
- **`PATCH /queue` with `current_item_id`** only moves the pointer. T-13's "skipped songs count as played" then follows from D2's derivation.

### D3. Repositories own the transaction and the lock

One concrete class per model, with no interface:

- **`PlaybackQueueRepository`:**
  - **`stateForOwner(int $userId): ?PlaybackQueue`** reads the queue with its items ordered by position, or returns null;
  - **`writeForOwner(int $userId, callable $write): void`** does the following inside `DB::transaction`:
    1. it creates the row if it is missing, with `insertOrIgnore` on the unique `user_id`, so two first writes cannot fail on the constraint;
    2. it reads the row again with `lockForUpdate()` and calls `$write($queue)`;
    3. when the callback returns, it raises `version` by one and saves.

    An exception thrown by the callback rolls everything back.
  - **`setCurrent(PlaybackQueue $queue, ?int $itemId)`**.
- **`PlaybackQueueItemRepository`:** `forQueue`, `count`, `find($queueId, $itemId)`, `append($queueId, list<QueueTrack>)`, `replaceAll($queueId, list<QueueTrack>)`, `moveTo($queue, $item, $index)`, `delete($item)` and `renumber($queueId)`.

Handlers are the only callers, and the query handler calls only `stateForOwner`. Each command handler does its checks inside the callback. The version check comes first, then the checks that depend on the stored state.

*Alternative:* handlers open the transaction with the `DB` facade. Rejected: the house rule keeps data access in repositories, and `UserRepository` already sets the precedent.

### D4. Commands, a query, and an answer from a fresh read

| Piece | Where |
|---|---|
| `ReplaceQueue(userId, tracks, currentIndex)` | `app/Application/PlaybackQueue/Commands` |
| `AddQueueItems(userId, version, tracks)` | same |
| `ChangeQueueCurrent(userId, version, itemId)` | same |
| `MoveQueueItem(userId, version, itemId, position)` | same |
| `RemoveQueueItem(userId, version, itemId)` | same |
| `GetQueue(userId)` → `QueueState` | `app/Application/PlaybackQueue/Queries` |
| `QueueState`, `QueueItemResult` (each with `toArray()` in the API's key order) | `app/Application/PlaybackQueue/Results` |
| `QueueTrack`, the validated input song, with `QueueTrack::SOURCES = ['spotify']` | `app/Application/PlaybackQueue` |
| `QueueVersionConflict`, `QueueFull`, `QueueItemNotFound`, `QueueItemNotPending`, `QueuePositionOutOfRange` (extend `UseCaseFailed`) | `app/Application/PlaybackQueue/Exceptions` |

- **A command handler returns nothing**, so the controller dispatches the command and then asks `GetQueue` for the answer. The state it returns may already include a newer write from another device. The client handles that, because it adopts the highest version (D8).
- **A missing queue** reads as an empty `QueueState` with version 0.
- **`ReplaceQueue` has no version.** It still runs inside `writeForOwner`, so it raises the version under the lock.

*Alternative:* commands that return the new state. Rejected: it breaks the CQRS rule that `ArchitectureTest` enforces.

### D5. Validation that answers with codes

- **The base class:** each endpoint has a form request under `app/Http/Requests/PlaybackQueue`, extending an abstract `QueueRequest`. Its `failedValidation()` throws an `HttpResponseException` with `{"error": code}`, status 422 and `no-store`, instead of Laravel's `{message, errors}`.
- **The code comes from the first failed field and rule:**

| Field and rule | Code |
|---|---|
| `tracks` missing or empty | `tracks_required` |
| `tracks` with more than 500 songs | `queue_full` |
| anything under `tracks.*` | `invalid_track` |
| `current_index` outside the list | `current_index_out_of_range` |
| anything else (`version`, `position`, `current_item_id`) | `invalid_request` |

- **The rules for a song:**
  - `source` must be in `QueueTrack::SOURCES`, and for Spotify `uri` must match `^spotify:track:[A-Za-z0-9]{22}$`;
  - `id` must equal the uri's last segment;
  - `title` must be a non-empty string of at most 500 characters;
  - `artists` must be a list of at most 50 strings of at most 500 characters each;
  - `album` must be null or a string of at most 500 characters;
  - `duration_ms` must be an integer of 0 or more;
  - `image` must be null or a URL of at most 2048 characters;
  - `playable` must be `true`;
  - `origin` must be null, or an object with a string `type` whose JSON encoding is at most 1024 bytes.

  Other keys of a song, such as `position`, `local` and `added_at`, are ignored and not stored.
- **Other fields:** `version` is a required integer of 0 or more, and `position` an integer of 0 or more. A position past the pending songs is a handler check, because it depends on the stored queue. `PATCH /queue` takes only `version` and `current_item_id`, both required in this change. Changes 2 and 3 add `mode`, `shuffle`, `repeat` and `restart`, and relax that rule to "at least one field".
- **The server never asks Spotify about a song.** It trusts the common format, as T-13 decided.

### D6. Routes and controllers

| Route | Name | Controller | Command or query |
|---|---|---|---|
| `GET queue` | `queue.show` | `ShowQueueController` | `GetQueue` |
| `PUT queue` | `queue.replace` | `ReplaceQueueController` | `ReplaceQueue` |
| `PATCH queue` | `queue.update` | `ChangeQueueCurrentController` | `ChangeQueueCurrent` |
| `POST queue/items` | `queue.items.add` | `AddQueueItemsController` | `AddQueueItems` |
| `PATCH queue/items/{item}` | `queue.items.move` | `MoveQueueItemController` | `MoveQueueItem` |
| `DELETE queue/items/{item}?version=` | `queue.items.remove` | `RemoveQueueItemController` | `RemoveQueueItem` |

- **`{item}`** is constrained with `whereNumber`. An item that belongs to no queue of the owner is `QueueItemNotFound`.
- **The shared trait:** the controllers live in `app/Http/Controllers/PlaybackQueue`. They share `Concerns/RespondsWithQueue`, which provides:
  - `queueResponse(int $userId)`, the 200 answer with `GetQueue`;
  - `queueFailure(UseCaseFailed $failure, int $userId)`.

  `queueFailure()` maps the failures to these answers:
  - 409 `{"error": "queue_version_conflict", "queue": {...}}`;
  - 422 `queue_full`;
  - 404 `queue_item_not_found`;
  - 422 `item_not_pending`;
  - 422 `position_out_of_range`.

  Every answer carries `no-store, private`. `App\Http\Controllers\PlaybackQueue\Concerns` joins `CONTROLLER_RULE_EXEMPT` in `tests/Unit/ArchitectureTest.php`.
- **`bootstrap/app.php`** adds `queue` and `queue/*` to the JSON 401 and JSON 419 paths.
- **`config/player.php`** holds `queue_poll_seconds`, set by `PLAYER_QUEUE_POLL_SECONDS` with a default of 30. `HandleInertiaRequests` shares it as `player.queuePollSeconds` for signed-in requests, so browser tests can shorten the wait without fake timers.

### D7. "Add to queue" beside the row

- **`resources/js/components/AddToQueueButton.vue`** is an icon `AeroButton` with the new `queue-add` icon. Its accessible name and `title` are "Add to queue", and it calls `useQueue().add(song)`.
  - **For a song that cannot play** it is `disabled`.
  - **While that song's edit is pending,** it gets `aria-disabled="true"` and ignores presses, but it is not `disabled`. A focused button that becomes `disabled` loses the focus to `<body>` in Chrome, so this keeps keyboard users in place.
- **`SongRow.vue` keeps its markup and hooks.** A small wrapper, `QueueableSongRow.vue`, puts `SongRow` and `AddToQueueButton` side by side in a flex row, and `Search.vue`, `library/Liked.vue` and `library/Playlist.vue` render it in place of `SongRow`. The search and library hooks therefore stay as they are. The service labels move from `SongRow.vue` to `resources/js/lib/sources.ts`, which the panel shares.
- **`AeroButton`** gives `aria-disabled="true"` the disabled look and drops its hover and press looks, so a busy button reads as unavailable while it keeps the focus.
- **What the client sends:** it strips each song to the common format plus `playable` before sending.
- **Album rows** get the button with T-24, which builds them.

*Alternative:* a button inside `SongRow`. Rejected: a button inside a button is invalid HTML, and turning the row into a `div` would break the search and library hooks.

### D8. `useQueue`, one queue per tab

`resources/js/composables/useQueue.ts` keeps its state at module level, like `useSpotifyPlayer`:

- **The state:** `queue` (the last adopted `QueueState`), `pendingAdds` (the uris whose add is queued or in flight), `pendingItems` (the item_ids being moved or removed) and `message` (text with its own timer).
- **Derived lists:** `current` and `upNext` come from `current_item_id` (D2).
- **`load()`** calls `GET /queue`.
- **Edits run one after another.** `add(song)`, `move(itemId, position)` and `remove(itemId)` chain onto one promise per tab.
  - Each edit reads the version when its turn comes, not when it was asked for. A second press on another row waits for the first edit and then uses the version from its answer.
  - An edit already queued for the same uri, or the same item, is ignored, which covers a double click.
  - An edit whose item is gone from the adopted queue by its turn is dropped without a request.
- **Each answer is handled by its status:**
  - **200:** adopt the state;
  - **409:** adopt `body.queue` and show "The queue changed on another device.", with no retry;
  - **422 `queue_full`:** show the full-queue message;
  - **401 or 419:** go to `/login`;
  - **any other 4xx:** `load()` again and show "Couldn't update the queue. Try again.";
  - **5xx or a network failure:** show "Couldn't update the queue. Try again." and keep the state;
  - **a successful add** shows "Added to queue".
- **Adopting** keeps the state with the highest `version`, so a poll that answers after an edit never brings back an older queue.
- **Sync:** `startSync(pollSeconds)` loads at once and chains a `setTimeout` while `document.visibilityState === 'visible'`.
  - On `visibilitychange` to visible, it loads and restarts the timer. When the tab hides, it clears the timer.
  - `stopSync()` runs when the layout unmounts, for example on logout, and also clears the state.
- **The JSON helper** reads the `XSRF-TOKEN` cookie like `useSpotifyPlayer`. `useSpotifyPlayer` is not changed here.

### D9. The panel and the status line

- **The "Queue" button:** `PlayerLayout.vue` puts it in the bar's `extra` slot, as an `AeroButton` with the new `queue` icon, `aria-expanded` and `aria-controls="queue-panel"`.
- **`resources/js/components/QueuePanel.vue`** is an `AeroGlassPanel`:
  - it is fixed above the bar (`bottom: var(--app-player-height)`), aligned to the end, `min(28rem, 100vw - 2 × gutter)` wide and at most 60 vh tall, and its list scrolls;
  - its heading is "Queue" with `tabindex="-1"` and gets the focus when the panel opens;
  - Escape closes it and focuses "Queue" again. The panel is not modal and does not trap the focus.
- **Rows:** an `AeroCover` of 40 px, the title, the artists, the duration, the service label, then the buttons.
  - The current song has only "Remove".
  - A pending song has "Move up", "Move down" and "Remove", each an icon `AeroButton` of 44x44 px with that accessible name, inside a `<li>` labelled by the song's title.
  - **`disabled` is used only at the ends of the list:** "Move up" on the first pending song and "Move down" on the last.
  - **While an edit of a row is queued or in flight,** that row's buttons get `aria-disabled="true"` and ignore presses, but keep the focus (D7). The other rows stay usable, and their edits wait in the chain (D8).
- **Focus after a move:** it goes back to the same button of the moved item. When that button becomes disabled at an end of the list, the focus goes to the other move button.
- **Focus after a removal:** it goes to the same button of the next row, or of the previous row when the removed row was the last, or to the panel heading when no rows are left.
- **Dragging:** pending rows are `draggable`. A drop on a row moves the dragged item to that row's index. A phone uses the buttons, because HTML5 drag and drop has no touch support.
- **The status line:** `PlayerLayout.vue` shows `useQueue().message` in a small glass pill above the bar, `role="status"` with `aria-live="polite"` (`data-part="queue-message"`), for 5 seconds.
- **New icons:** `queue.svg` (three lines), `queue-add.svg` (three lines and a plus), and `move-up.svg`, `move-down.svg` and `remove.svg` for the panel buttons, all 24x24 with `fill="currentColor"`. Each gets its row in `resources/aero/CREDITS.md` and its name in `AeroIconName`.

*Alternative:* the bar's notice area for the queue messages. Rejected: it shows the player's own notices, such as "Connect Spotify…", which must stay visible.

### D10. Tests

- **Feature, `tests/Feature/PlaybackQueue/`:**
  - reading, with exact keys, the empty queue and played songs;
  - the 401 table and `no-store`;
  - add, replace, change the current song, move and remove, with their versions;
  - the 409 with the queue in its body, and two writes with the same version;
  - every 422 and 404 code, `queue_full` with a factory of 500 items, and the origin kept as sent;
  - `Http::preventStrayRequests()`, so no test reaches Spotify.
- **Feature, `tests/Feature/PlaybackQueue/QueueRepositoriesTest.php`** (unit tests have no database): the repositories' renumbering, the moves, removing the current song, and `original_position` on append.
- **Browser, `tests/Browser/QueuePanelTest.php` and `AddToQueueTest.php`**, with `PLAYER_QUEUE_POLL_SECONDS` set low and the fake SDK:
  - the panel, empty and with played songs hidden, Escape, and 375 px;
  - Move up, Move down and Remove, with the focus kept;
  - dragging with Pest's `drag()`, then a reload;
  - "Add to queue" from the three pages, a disabled unplayable row and a double click;
  - the focus kept on "Add to queue" while its request is slow, with the route answering late;
  - two rows added before the first answer, checking the versions of both requests on the server;
  - no `pause()` and no start request while editing;
  - 503, network failure (the route blocked), 409 and `queue_full`;
  - without Spotify;
  - two pages with the same session for "another device";
  - visibility simulated through `document.visibilityState` and `visibilitychange`, counting `GET /queue` on the server with a request log;
  - the bounded focus walks of the browser-tab memory note.
- **Regression:** the search, playlist, Liked Songs, player bar and architecture tests keep passing.

## Risks / Trade-offs

- [The panel says "Now playing" for the queue's current song while the bar may play something else] → Accepted for change 1, as T-13 decided. Change 2 aligns them.
- [A poll can answer after an edit with an older version] → The client adopts the highest version (D8).
- [Renumbering on every write] → At most 500 rows in one transaction, on the owner's only queue.
- [Drag and drop is mouse only] → The buttons cover the keyboard, screen readers and touch.
- [The poll costs a request every 30 s per visible tab] → It is a single indexed read for one owner, and hidden tabs never poll.
- [Unused columns until changes 2 and 3] → They hold only their defaults, and the API already exposes them.
- [The row lock is not exercised by the tests] → Feature tests send requests one after another, so "Two writes with the same version" checks only the version number. `lockForUpdate` inside `writeForOwner` is checked in code review, and every write goes through that one method (D3).
- [The server saves an edit but the answer never reaches the client (timeout or a dropped connection)] → The client keeps the old version, so its next edit gets a 409 and shows "The queue changed on another device." although the change was its own. Accepted: the client adopts the queue from that answer, so nothing is lost, and the owner repeats the edit if needed.

## Migration Plan

Two new migrations, applied by the deploy's `php artisan migrate`. Nothing existing changes. Rollback means reverting the commits and rolling back the two migrations, which only drops the new tables.

## Backlog follow-ups (applied at /ship)

- **T-13:** stays open with `change: playback-queue`. Record that change 1 shipped and that the album rows of the "Add to queue" outline move to T-24.
- **T-24:** put `AddToQueueButton` beside each track row.
- **T-10:** `QueueTrack::SOURCES` and the uri rule are where `youtube_music` joins.
- **T-14:** `PUT /queue` keeps the `origin` it is given. Playing a playlist uses it in change 2.
- **T-13, for changes 2 and 3:** their specs MODIFY two parts of this capability:
  - **"The queue is read in full"** loses its "until shuffle, repeat and Spotify contexts exist" clause once `shuffle`, `repeat`, `mode`, `context` and `has_more` carry real values;
  - **"A malformed queue request is rejected"** gets a new "PATCH /queue … no field to change" row, since `PATCH /queue` takes `mode`, `shuffle`, `repeat` and `restart` and then needs only one of them.
