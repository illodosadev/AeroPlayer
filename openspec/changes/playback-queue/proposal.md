# Proposal

## Why

T-13 (playback queue, backlog `T-13`) is split in three changes. This is the first: the queue lives on the server, so it is the same on every device (D3), and the owner can see and edit it. Change 2 (queue-driven playback) and change 3 (shuffle and repeat) build on it, and T-10 adds YouTube Music to the same model.

## What Changes

- **A queue per owner** in two tables, for any service. Each item keeps its `source`, the common song fields and an opaque `origin` for T-14. The queue holds at most 500 songs: played, current and pending.
- **A JSON API under `/queue`:**
  - `GET /queue` reads the full state;
  - `PUT /queue` replaces the queue;
  - `PATCH /queue` moves the current song;
  - `POST /queue/items` adds songs;
  - `PATCH /queue/items/{item}` moves a pending song;
  - `DELETE /queue/items/{item}` removes one.
- **Versioning:** every write but `PUT /queue` carries the client's `version`. A stale one answers 409 `queue_version_conflict` with the current queue. Writes raise the version under a row lock.
- **Errors** answer with an `error` code, and a request without a session answers 401 `unauthenticated`. Only Spotify songs are accepted for now.
- **"Add to queue"** goes on the song rows of the search, Liked Songs and own playlist pages. It is disabled for songs that cannot play and never interrupts playback. A tab sends its edits one after another, so no press is lost.
- **A "Queue" panel** opens from the player bar. It shows "Now playing" and "Next up", and songs can be reordered with the mouse or with "Move up" and "Move down", or removed.
- **Sync:** the client reads the queue on load, when the tab becomes visible, and every 30 seconds while it stays visible.
- **Messages** show in a status line above the bar.

## Non-goals

- Playback does not follow the queue yet: "Now playing" is the queue's current song. Queue-driven playback, lists that replace the queue, continuation and the D4 failures come in change 2.
- Shuffle and repeat (change 3). Their fields exist with their defaults.
- YouTube Music (T-10), album rows (T-24), "Play next", "Clear queue", and saving the queue as a playlist (T-14).
- Websockets: the hosting allows none (T-04).

## Capabilities

### New Capabilities

- `playback-queue`: the owner's queue on the server, its API, versioning and validation, "Add to queue", the panel and sync between devices.

### Modified Capabilities

None. The rows of `music-search` and `music-library` gain a button, and their requirements still hold.

## Impact

- **New:**
  - two tables with their models and repositories;
  - the `PlaybackQueue` use cases;
  - six controllers;
  - `config/player.php`;
  - the `useQueue` composable;
  - the panel and the row button;
  - two icons.
- **Changed:**
  - `routes/web.php`;
  - `bootstrap/app.php`;
  - `PlayerLayout.vue`;
  - the search, Liked Songs and playlist pages;
  - `RoutesTest`.
- **Tests:** feature, repository and browser tests; existing suites keep passing.
- **Backlog:** `T-13` gets `change: playback-queue` and stays open until change 3.
