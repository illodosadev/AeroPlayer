# Proposal

## Why

The owner wants AeroPlayer built with CQRS. Today controllers, the console command and the Inertia middleware call repositories and the Spotify OAuth service directly, so reads and writes are mixed into the entry points. The app is still small (login, owner account, Spotify connection), so adopting CQRS now costs little and every later feature, starting with `spotify-api-client`, follows it from day one.

## What Changes

- Every use case becomes a **Command** (changes state, returns nothing) or a **Query** (reads state, returns data), each with its own Handler in `app/Application/<Context>/`.
- Two small concrete buses, `CommandBus::dispatch()` and `QueryBus::ask()`, resolve the handler by naming convention from the container.
- A Command that cannot complete throws a domain exception. The entry point turns it into the same message or validation error as today.
- Queries return plain data (read objects, enums or scalars), never Eloquent models.
- Controllers, console commands and middleware only validate input, handle the HTTP or console concerns (session, state, prompts, redirects) and dispatch. They no longer use repositories, models or the Spotify OAuth service.
- Existing code is migrated: login and logout, `app:create-owner`, connecting and disconnecting Spotify, the Spotify settings page and the shared `spotify` props.
- Handlers keep using the concrete repositories in `app/Repositories`. The Spotify repository's write methods take the user id instead of a `User` model, because commands carry ids.
- Pest architecture tests enforce the rule. AGENTS.md, `openspec/config.yaml` and the README document it.

No behavior changes: every existing feature and browser test passes unchanged.

## Non-goals

- Separate read and write repositories, read models, projections or event sourcing.
- A queue, async dispatch, or bus middleware such as logging or transactions.
- Interfaces for repositories (still forbidden), or new behavior for any page.
- Pages that only render a view with no data (dashboard, styleguide, the root redirect) do not get a Query.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

None. This is an internal refactor with no change in system behavior, so the change sets `skip_specs: true`. The existing specs and their tests are the proof that nothing changed.

## Impact

- **New:**
  - `app/Bus/` (the `Command` and `Query` markers, `CommandBus`, `QueryBus`);
  - `app/Application/Auth`, `app/Application/Owner` and `app/Application/Spotify` (commands, queries, handlers, exceptions and read objects).
- **Changed:**
  - `AuthenticatedSessionController` and `LoginRequest`;
  - `CreateOwner`;
  - `SpotifyConnectionController` and `SpotifySettingsController`;
  - `HandleInertiaRequests`;
  - `SpotifyConnectionRepository`, whose write methods now take the user id.
- **Tests:**
  - new unit tests for the buses and architecture tests;
  - the Spotify repository test adapted to id arguments;
  - feature and browser tests unchanged.
- **Docs:** `AGENTS.md`, `openspec/config.yaml` and `README.md`.
- **Order:** this change goes before `spotify-api-client`. There is no linked backlog task. The follow-ups for T-05 and T-06 are listed in design.
