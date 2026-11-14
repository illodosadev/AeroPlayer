# Tasks

No spec deltas (`skip_specs: true`). The existing feature and browser tests are the behavior contract: they must pass **unchanged**.

## 1. Route table guard

- [x] 1.1 Add `tests/Feature/RoutesTest.php` (design D5) with the pinned table of every route in the `web` group: methods, URI, name and middleware, from design "Context". Add the check that each of those routes runs `__invoke` on an `App\Http\Controllers` class or is Laravel's `RedirectController`, and mark that check `->todo()` until step 4.1.

  Verify that the table test passes against today's routes, so it proves later steps keep them.

## 2. Pages without data

- [x] 2.1 Replace the closures for `/`, `dashboard` and `styleguide` with `RedirectHomeController` (design D3), `ShowDashboardController` and `ShowStyleguideController`, keeping their names and middleware (design D4). Verify that `tests/Feature/DashboardTest.php`, `StyleguideTest.php`, `FoundationTest.php` and `RoutesTest.php` pass unchanged.

## 3. Login and logout

- [x] 3.1 Split `AuthenticatedSessionController` into `Auth/ShowLoginController`, `Auth/LogInController` and `Auth/LogOutController`, keeping each method's body (design D1). Point `routes/auth.php` at them and delete the old class. Verify that `tests/Feature/OwnerAuthTest.php`, `tests/Feature/Auth`, `tests/Browser/OwnerAuthBrowserTest.php` and `RoutesTest.php` pass unchanged.

## 4. Spotify

- [x] 4.1 Split the Spotify controllers (design D1, D2):
  - `Spotify/StartSpotifyConnectionController`, with the public `STATE_KEY`;
  - `Spotify/FinishSpotifyConnectionController`;
  - `Spotify/DisconnectSpotifyController`;
  - `Spotify/ShowSpotifySettingsController`;
  - the trait `Spotify/Concerns/RedirectsToSpotifySettings`.

  Point `routes/web.php` at them, delete `SpotifyConnectionController` and `SpotifySettingsController`, and turn on the invokable check in `RoutesTest.php` by removing `->todo()`. Add to `tests/Unit/ArchitectureTest.php` the expectation that the classes in `App\Http\Controllers` are invokable with no public methods besides `__construct` and `__invoke`, ignoring `Controller` and `Concerns` (design D5).

  Verify that `tests/Feature/Spotify`, `tests/Browser/SpotifySettingsTest.php`, `RoutesTest.php` and the arch test pass. Also verify that the arch test fails when a throwaway controller has a second public method, and remove that controller afterwards.

## 5. Documentation

- [x] 5.1 Document the rule in the same words in AGENTS.md (Code conventions), the `context:` block of `openspec/config.yaml` and the README (Conventions), following design D6. Verify that `openspec validate single-action-controllers --strict` passes and that the three texts name the same folders and exceptions.

## 6. Integration

- [x] 6.1 Run Pint on the changed PHP files. Then verify that `npm run build` and the full `vendor/bin/pest` run pass together, that `git diff --stat -- tests/Feature tests/Browser` shows no existing test changed, and that `php artisan route:list` lists no closure in the `web` group.

## Workflow follow-up

- `/review-change single-action-controllers` before `/opsx-apply`.
- At `/ship`:
  - apply the backlog follow-ups from design (T-05, T-06, T-18);
  - archive the change (no spec sync: `skip_specs`).
- Then `/opsx-propose spotify-api-client`.
