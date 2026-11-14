# Design

## Context

See proposal.md, "Why". The routes today:

| Method and URI | Name | Middleware | Action |
|---|---|---|---|
| GET `/` | `home` | web | closure: redirect by `auth()->check()` |
| GET `dashboard` | `dashboard` | web, auth | closure: render `Dashboard` |
| GET `styleguide` | `styleguide` | web, auth | closure: render `Styleguide` |
| GET `login` | `login` | web, guest | `AuthenticatedSessionController@create` |
| POST `login` | (none) | web, guest | `AuthenticatedSessionController@store` |
| POST `logout` | `logout` | web, auth | `AuthenticatedSessionController@destroy` |
| GET `spotify/connect` | `spotify.connect` | web, auth | `SpotifyConnectionController@redirect` |
| GET `spotify/callback` | `spotify.callback` | web, auth | `SpotifyConnectionController@callback` |
| DELETE `spotify/connection` | `spotify.disconnect` | web, auth | `SpotifyConnectionController@destroy` |
| any `settings` | `settings` | web, auth | `Route::redirect` to `/settings/spotify` |
| GET `settings/spotify` | `spotify.settings` | web, auth | `SpotifySettingsController@show` |

Laravel also registers `up` and `storage/{path}` as closures outside the `web` group.

- `SpotifyConnectionController` shares the session key `spotify_oauth_state` between `redirect` and `callback`, plus two private helpers, `failed()` and `toSettings()`, that build the redirect to `/settings/spotify` with the flashed `spotifyMessage`.
- No test names a controller class.
- Pest's arch plugin offers `toBeInvokable()` and `toHavePublicMethodsBesides()`.

## Goals / Non-Goals

**Goals:**

- One class per route, each with one action, so a route reads as "this URI runs this controller, which dispatches this command or query".
- A rule the architecture test enforces, plus a pinned route table that proves the URIs, names and middleware did not change.

**Non-Goals:**

- Moving HTTP concerns out of controllers. The CQRS rule (`adopt-cqrs`) already settles where they live.
- Changing `Route::redirect('settings', ...)` or Laravel's internal routes.

## Decisions

### D1. Naming and layout

```
app/Http/Controllers/
  Controller.php                          abstract base, unchanged
  RedirectHomeController.php              GET /
  ShowDashboardController.php             GET dashboard
  ShowStyleguideController.php            GET styleguide
  Auth/ShowLoginController.php            GET login
  Auth/LogInController.php                POST login
  Auth/LogOutController.php               POST logout
  Spotify/StartSpotifyConnectionController.php    GET spotify/connect
  Spotify/FinishSpotifyConnectionController.php   GET spotify/callback
  Spotify/DisconnectSpotifyController.php         DELETE spotify/connection
  Spotify/ShowSpotifySettingsController.php       GET settings/spotify
  Spotify/Concerns/RedirectsToSpotifySettings.php trait (D2)
```

- Each name is the action in the imperative plus `Controller`: `Show…` for a page and a verb for anything else.
- The names follow the commands where one exists (`LogOutController` dispatches `LogOutOwner`).
- The contexts match `app/Application` (`Auth`, `Spotify`). App-wide pages stay at the root.
- Each controller extends `Controller` and has only `__invoke`, plus an optional constructor for the buses.

*Alternatives:*

- Laravel's resource method names (`index`, `show`, `store`…) on several small controllers. Rejected: the owner wants one action per class.
- One folder per route. Rejected: it adds a level for one file.

### D2. Code shared by the Spotify controllers

- **The session key:** `StartSpotifyConnectionController::STATE_KEY` becomes a public constant, and `FinishSpotifyConnectionController` reads it. A constant is not an action, so the rule allows it.
- **The redirect helpers:** `failed()` and `toSettings()` move to the trait `Spotify\Concerns\RedirectsToSpotifySettings` as protected methods. `FinishSpotifyConnectionController` and `DisconnectSpotifyController` use it.

*Alternatives:*

- A `SpotifyController` base class. Rejected: inheritance just to share two helpers, and the base would itself be a controller without an action.
- Copying the helpers into each controller. Rejected: the message shape (`tone`, `text`, `detail`) would live in two places.

### D3. The home redirect reads the request, not the `auth()` helper

`RedirectHomeController` decides with `$request->user() !== null`, the same check as `auth()->check()` on the `web` guard. This keeps the CQRS rule that entry points do not reach state through helpers.

### D4. Routes

- `routes/web.php` and `routes/auth.php` register each controller by class: `Route::get('login', ShowLoginController::class)->name('login')`.
- The groups, the middleware, the names and the order stay as they are.
- `POST login` keeps having no name.

### D5. Enforcement

- **`tests/Unit/ArchitectureTest.php`:** the classes in `App\Http\Controllers` must be invokable and must not have public methods besides `__construct` and `__invoke`. The check ignores the abstract base `Controller` and the `Concerns` traits.
- **`tests/Feature/RoutesTest.php`:**
  - every route in the `web` middleware group runs `__invoke` on an `App\Http\Controllers` class, or is Laravel's `RedirectController`;
  - the route table (methods, URI, name and middleware of every `web` route) equals the table in Context, which pins the behavior this refactor must keep.

### D6. Documentation

- AGENTS.md, under Code conventions, gets a "Single-action controllers" bullet: invokable only, named after the action, grouped by context, no closures in the route files, only `Route::redirect` allowed.
- One line goes in the `context:` block of `openspec/config.yaml`, and one bullet in the README, under Conventions.
- The text matches in all three places.

## Risks / Trade-offs

- [A route loses its name, middleware or method in the move] → `RoutesTest` pins the route table, and the existing feature and browser tests cover each route.
- [The login controllers drift apart, for example the session regeneration] → `LogInController` keeps `store()`'s body and `LogOutController` keeps `destroy()`'s body, line for line. `OwnerAuthTest` and `OwnerAuthBrowserTest` cover both.
- [More files] → Accepted. It is the owner's choice, and each file is a dozen lines.
- [The arch plugin also checks the traits] → The `Concerns` namespace is ignored explicitly. The trait methods are protected, so no public action can hide there.

## Migration Plan

This is a pure refactor with no data migration. Deploy as usual. Rollback means reverting the commit.

## Backlog follow-ups (applied at /ship)

- **T-05:** any route that `spotify-api-client` adds is an invokable controller in `app/Http/Controllers/Spotify`.
- **T-06:** `GET /spotify/token` is `Spotify/ShowSpotifyPlaybackTokenController`, which asks `GetSpotifyPlaybackToken`.
- **T-18:** `/settings/appearance` gets its own invokable controller in the context of its feature, for example `Appearance/ShowAppearanceSettingsController`, just as the Spotify settings page lives in `Spotify/`.
