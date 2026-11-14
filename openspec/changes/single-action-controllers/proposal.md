# Proposal

## Why

The owner wants "one controller, one action". Today two controllers bundle three actions each (`AuthenticatedSessionController` and `SpotifyConnectionController`), and three routes are closures in `routes/web.php`. With CQRS just adopted (`adopt-cqrs`), one invokable controller per route maps each HTTP entry point to one command or query. Doing it now, before `spotify-api-client` adds more routes, keeps the cost to a handful of small files.

## What Changes

- Every controller is invokable: `__invoke` is its only public action, next to an optional constructor.
- Controllers are grouped by context, like `app/Application`, and named after their action:
  - `Auth/ShowLoginController`, `Auth/LogInController` and `Auth/LogOutController` replace `AuthenticatedSessionController`;
  - `Spotify/StartSpotifyConnectionController`, `Spotify/FinishSpotifyConnectionController` and `Spotify/DisconnectSpotifyController` replace `SpotifyConnectionController`;
  - `Spotify/ShowSpotifySettingsController` replaces `SpotifySettingsController`;
  - `RedirectHomeController`, `ShowDashboardController` and `ShowStyleguideController` replace the closures for `/`, `/dashboard` and `/styleguide`.
- `Route::redirect('settings', ...)` stays: it is declarative and has no code.
- The URIs, route names, HTTP methods and middleware do not change, so the frontend, Ziggy and the tests keep working.
- The architecture test enforces the rule, and a route test checks that every route in the `web` group points to an invokable controller and that the route table did not change.
- AGENTS.md, `openspec/config.yaml` and the README document the rule.

No behavior changes: every existing feature and browser test passes unchanged.

## Non-goals

- Changing any URI, route name, middleware, page or message.
- Resource controllers, route model binding or a new routing layout.
- Laravel's internal routes (`up`, `storage/{path}`), which are not in the `web` group.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

None. This is an internal refactor with no change in system behavior, so the change sets `skip_specs: true`. The existing specs and their tests are the proof that nothing changed.

## Impact

- **New:** ten invokable controllers in `app/Http/Controllers` (three at the root, three in `Auth/` and four in `Spotify/`), plus a small trait for the Spotify settings redirect with its flashed message.
- **Removed:** `AuthenticatedSessionController`, `SpotifyConnectionController` and `SpotifySettingsController`.
- **Changed:** `routes/web.php` and `routes/auth.php`, which point to the new controllers and keep no closures.
- **Tests:**
  - `tests/Unit/ArchitectureTest.php` gains the invokable rule;
  - a new feature test pins the route table;
  - the existing feature and browser tests stay unchanged.
- **Docs:** `AGENTS.md`, `openspec/config.yaml` and `README.md`.
- **Order:** this change goes before `spotify-api-client`. There is no linked backlog task. The follow-ups for T-05, T-06 and T-18 are listed in design.
