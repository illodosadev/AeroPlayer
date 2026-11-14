# Tasks

No spec deltas (`skip_specs: true`). The existing feature and browser tests are the behavior contract: they must pass **unchanged**, except the Spotify repository test, whose arguments become ids (design D6).

## 1. Buses and conventions

- [x] 1.1 Create `app/Bus` (design D2):
  - the `Command` marker and the `Query` marker with `@template TResult`;
  - the final classes `CommandBus` and `QueryBus`, which receive `Illuminate\Contracts\Container\Container` in the constructor and resolve `<Message>Handler` from it on every call;
  - `HandlerNotFound` (`LogicException`), whose message names only the message class.

  Add `app/Application/UseCaseFailed.php` (design D1, D3).

  Verify with `tests/Unit/Bus/CommandBusTest.php` and `QueryBusTest.php`. They give the buses a `new Container()` and use test-only messages with names unique to these files, such as `BusTestPing` (design D7):
  - `dispatch()` calls the handler and returns nothing;
  - `ask()` returns the handler's result;
  - a handler gets its constructor dependencies injected;
  - a message without a handler throws `HandlerNotFound`, and its message does not contain the payload.
- [x] 1.2 Add `tests/Unit/ArchitectureTest.php` (design D7):
  - the reflection test over `app/Application`: messages, handlers, `void` or non-model return types, and exceptions extending `UseCaseFailed`;
  - the arch expectations that `App\Application` does not use `Illuminate\Http\Request`, `Inertia` or `App\Http`.

  The entry-point expectation comes in step 2.1, because an empty list would check nothing. Verify that the test passes with `app/Application` still empty, and that a throwaway command whose handler returns a value makes it fail. Remove the throwaway class afterwards.

## 2. Owner account (console)

- [x] 2.1 Create `app/Application/Owner` (design D5):
  - `GetOwnerAccount` with `GetOwnerAccountHandler`, returning `?Results\OwnerAccount`;
  - `CreateOwnerAccount` and `ReplaceOwnerAccount`, both with `#[\SensitiveParameter]` on the password;
  - the exceptions `OwnerAccountExists` and `OwnerAccountMissing`.

  Rewrite `App\Console\Commands\CreateOwner` to ask and dispatch through the buses, keeping its prompts and validation. Create the entry-point arch expectation in `tests/Unit/ArchitectureTest.php` with `App\Console` as its first entry (design D7). Verify that `tests/Feature/CreateOwnerCommandTest.php` and `UserRepositoryTest.php` pass unchanged, and that the arch test passes.

## 3. Login and logout

- [x] 3.1 Create `app/Application/Auth` (design D5):
  - `LogInOwner(email, password, remember, ip)`, with the rate limit, `Auth::attempt`, hit and clear moved from `LoginRequest`;
  - `LogOutOwner`;
  - the exceptions `InvalidCredentials` and `TooManyLoginAttempts(seconds)`.

  Slim `LoginRequest` down to validation and email normalisation. `AuthenticatedSessionController` then:
  - dispatches;
  - fires `Lockout($request)` on `TooManyLoginAttempts`;
  - maps both exceptions to the same `ValidationException` (`auth.failed`, `auth.throttle`);
  - keeps the session regeneration, the invalidation and `Inertia::clearHistory()`.

  Add `App\Http\Requests` and `AuthenticatedSessionController` to the arch list. Verify that `tests/Feature/OwnerAuthTest.php`, `tests/Feature/Auth`, `tests/Browser/OwnerAuthBrowserTest.php` and the arch test pass unchanged.

## 4. Spotify

- [x] 4.1 Make `SpotifyConnectionRepository::storeForOwner` and `deleteForOwner` take `int $userId` (design D6). In the same step:
  - change their two callers in `SpotifyConnectionController` to pass `$request->user()->getKey()`, so the suite stays green until step 4.3 replaces them;
  - update `tests/Feature/Spotify/SpotifyConnectionRepositoryTest.php` to pass ids.

  Verify that `SpotifyConnectionRepositoryTest.php` passes, and that `SpotifyCallbackTest.php` and `SpotifySettingsTest.php` pass unchanged.
- [x] 4.2 Create the Spotify queries (design D4, D5):
  - `GetSpotifySettings`, returning `Results\SpotifySettings`;
  - `GetSpotifyStatus`, returning `?SpotifyConnectionStatus`;
  - `GetSpotifyAuthorizationUrl(state)`, which throws `SpotifyNotConfigured`.

  Make `SpotifySettingsController`, `HandleInertiaRequests` and `SpotifyConnectionController::redirect` ask them. Guests still skip the status query. Add `SpotifySettingsController` and `App\Http\Middleware` to the arch list. Verify that `tests/Feature/Spotify/SpotifySettingsTest.php`, `SpotifyConnectTest.php` and the arch test pass unchanged.
- [x] 4.3 Create the Spotify commands (design D3, D5):
  - `ConnectSpotify(userId, code)`, throwing `SpotifyConnectionFailed(reason)`;
  - `DisconnectSpotify(userId)`.

  `SpotifyConnectionController` keeps the session state check and the `error` and `code` mapping before dispatching. Replace the whole entry-point list with the namespaces `App\Http\Controllers`, `App\Http\Middleware`, `App\Http\Requests` and `App\Console`. Verify that `tests/Feature/Spotify/SpotifyCallbackTest.php`, `SpotifySettingsTest.php`, `tests/Browser/SpotifySettingsTest.php` and the arch test pass unchanged.

## 5. Documentation

- [x] 5.1 Document the rule in the same words in three places (design D8):
  - in AGENTS.md, under Code conventions, a "CQRS" bullet:
    - commands and queries with handlers in `app/Application/<Context>`;
    - the two buses;
    - commands return nothing, and failures are `UseCaseFailed` exceptions;
    - queries return `Results/` objects, enums or scalars, never models;
    - entry points only dispatch, and that includes the helpers `auth()`, `app()` and `request()`;
    - handlers are the only repository callers.

    Rewrite the repository bullet next to it so the two agree: repositories are injected only into handlers, and `unique` and `exists` stay exempt as queries Laravel runs internally;
  - one line in the `context:` block of `openspec/config.yaml`;
  - one bullet in the README, under Conventions.

  Verify that `openspec validate adopt-cqrs --strict` passes, and that the three texts name the same folders and classes.

## 6. Integration

- [x] 6.1 Run Pint on the changed PHP files. Then verify that `npm run build` and the full `vendor/bin/pest` run pass together, and that `git diff --stat` shows no feature or browser test changed besides `SpotifyConnectionRepositoryTest.php`.

## Workflow follow-up

- `/review-change adopt-cqrs` before `/opsx-apply`.
- At `/ship`:
  - apply the backlog follow-ups from design (T-05, T-06);
  - archive the change (no spec sync: `skip_specs`).
- Then `/opsx-propose spotify-api-client`, following the new rule.
