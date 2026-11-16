# Tasks

Every feature test calls `Http::preventStrayRequests()`, so no test reaches Spotify. Browser tests run with `PLAYER_QUEUE_POLL_SECONDS` set low, with the fake SDK, and with no `composer dev` running.

## 1. Data

- [ ] 1.1 Add the migrations for `playback_queues` and `playback_queue_items`, the `PlaybackQueue` and `PlaybackQueueItem` models with their casts, and their factories (design D1).
- [ ] 1.2 Add `PlaybackQueueRepository` (`stateForOwner`, `writeForOwner` with `insertOrIgnore` and `lockForUpdate`, `setCurrent`) and `PlaybackQueueItemRepository` (`forQueue`, `count`, `find`, `append`, `replaceAll`, `moveTo`, `delete`, `renumber`) (design D2, D3). Verify in `tests/Feature/PlaybackQueue/QueueRepositoriesTest.php`, a feature test because unit tests have no database:
  - dense positions after an append, a move and a removal;
  - `original_position` on append;
  - `nextAfter` gives the song after the current one, or null for the last;
  - an exception inside `writeForOwner` rolls back and keeps the version.

## 2. Use cases

- [ ] 2.1 Add `QueueTrack`, `QueueState`, `QueueItemResult`, the `GetQueue` query and the five exceptions (design D4). Verify that `tests/Unit/ArchitectureTest.php` accepts them.
- [ ] 2.2 Add `ReplaceQueue`, `AddQueueItems` and `ChangeQueueCurrent` with their handlers, including the version check, the 500 limit and the empty-queue rule (design D2, D4).
- [ ] 2.3 Add `MoveQueueItem` and `RemoveQueueItem` with their handlers, including the pending and position checks (design D2, D4).

## 3. API

- [ ] 3.1 Add the abstract `QueueRequest` with the code mapping, and the five write requests with the song rules (design D5).
- [ ] 3.2 Add the six routes and controllers, the `RespondsWithQueue` trait, with `PlaybackQueue\Concerns` added to `CONTROLLER_RULE_EXEMPT`, and the `RoutesTest` rows (design D6). Verify that `tests/Unit/ArchitectureTest.php` and `tests/Feature/RoutesTest.php` pass.
- [ ] 3.3 Add the JSON 401 and 419 paths in `bootstrap/app.php`, `config/player.php` and the shared `player.queuePollSeconds` prop (design D6). Verify in `tests/Feature/PlaybackQueue/QueueReadTest.php`:
  - "Reading a stored queue";
  - "Played songs stay in the queue";
  - "No queue yet";
  - "A visitor without a session", every row;
  - "Queue answers are not cached";
  - the shared prop is 30 by default.
- [ ] 3.4 Verify in `tests/Feature/PlaybackQueue/QueueWriteTest.php`:
  - "Adding a song saves it and raises the version";
  - "Adding to an empty queue makes the song current";
  - "The origin is kept as it is";
  - "The same song twice";
  - "Replacing with an old version";
  - "A replacement that cannot apply", every row;
  - "Moving forward marks the skipped songs as played";
  - "Moving to an unknown song".
- [ ] 3.5 Verify in `tests/Feature/PlaybackQueue/QueueEditTest.php`:
  - "Moving a song to the front of the pending songs";
  - "A move that cannot apply", every row;
  - "Removing a pending song";
  - "Removing the current song";
  - "Removing the last song, which is the current one";
  - "Removing an unknown song".
- [ ] 3.6 Verify in `tests/Feature/PlaybackQueue/QueueRulesTest.php`:
  - "An invalid song", every row;
  - "A malformed write", every row;
  - "A change from another device", the API part;
  - "Two writes with the same version";
  - `queue_full` on `POST /queue/items`, with a factory of 500 items.

## 4. Frontend

- [ ] 4.1 Add the `queue`, `queue-add`, `move-up`, `move-down` and `remove` icons with their `CREDITS.md` rows and `AeroIconName` entries, and add `useQueue` with its sync, version adoption, edit chain and status handling (design D8, D9). Verify that `npm run types` passes.
- [ ] 4.2 Add `QueuePanel.vue`, the "Queue" button and the status line in `PlayerLayout.vue` (design D9). Verify in `tests/Browser/QueuePanelTest.php`:
  - "Seeing the queue";
  - "Empty queue";
  - "Closing the panel with Escape";
  - "The panel on a phone";
  - "Spotify not connected";
  - the "Queue" button keeps the bar's touch area.
- [ ] 4.3 Add "Move up", "Move down", "Remove" and dragging to the panel, with `aria-disabled` rows while their edit is pending and the focus rules after a move or a removal (design D9). Verify in `tests/Browser/QueuePanelTest.php`:
  - "Reordering and removing songs";
  - "Reordering with the keyboard", including the focus after each move;
  - "The ends of the list";
  - "Reordering while a song plays".
- [ ] 4.4 Add `AddToQueueButton.vue` beside the rows of `Search.vue`, `library/Liked.vue` and `library/Playlist.vue`, `disabled` only for unplayable songs and `aria-disabled` while pending (design D7). Verify in `tests/Browser/AddToQueueTest.php`:
  - "Adding a song from a row", every row;
  - "Songs that cannot play cannot be added";
  - "A double click adds the song once";
  - "Adding does not play the row";
  - "Adding with the keyboard keeps the focus", with "POST /queue/items" answering late;
  - "Adding two songs from two rows in a row", checking the versions on the server;
  - "The queue is full", on screen.
- [ ] 4.5 Verify the failure and sync scenarios in `tests/Browser/QueueSyncTest.php`:
  - "The server fails during an edit", both with 503 and with the network down;
  - "A stale version from another device";
  - "The same queue on another device";
  - "Returning to the tab shows changes from another device";
  - "A visible tab reads the queue every 30 seconds", with the interval shortened and the wait scaled to it;
  - "A hidden tab does not read the queue".

## 5. Integration

- [ ] 5.1 Run Pint on the changed PHP files and Prettier on the changed frontend files. Then verify that `openspec validate playback-queue --strict`, `npm run types`, `npm run build` and the full `vendor/bin/pest` run pass together. The search, library, player and architecture tests must pass unchanged.
- [ ] 5.2 The owner opens the queue on two real devices, adds songs from search and Liked Songs on one, and checks that the other shows them within 30 seconds. Record the date and the result here.

## Workflow follow-up

- `/review-change playback-queue` before `/opsx-apply`.
- At `/ship`:
  - create the `playback-queue` capability;
  - keep T-13 open for changes 2 and 3;
  - apply the backlog follow-ups from design (T-13, T-24, T-10, T-14).
