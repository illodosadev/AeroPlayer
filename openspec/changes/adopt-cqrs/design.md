# Design

## Context

See proposal.md, "Why". The code in `app/` today:

- `AuthenticatedSessionController` and `LoginRequest::authenticate()` handle login. The rate limit and `Auth::attempt` live in the form request.
- The console command `CreateOwner` calls `UserRepository::findOwner`, `createOwner` and `replaceOwner` directly.
- `SpotifyConnectionController` and `SpotifySettingsController` call `SpotifyOAuth` and `SpotifyConnectionRepository`. The OAuth calls return `SpotifyTokens|ConnectFailure` and `SpotifyAccount|ConnectFailure`.
- `HandleInertiaRequests` resolves `SpotifyConnectionRepository` with `app()` for the shared `spotify` props.
- The repository rule stays in force: every model query goes through a concrete class in `app/Repositories`, with no interfaces.

Pest 4 ships the arch plugin (`pestphp/pest-plugin-arch`), so no new dependency is needed.

## Goals / Non-Goals

**Goals:**

- One obvious place per use case, with reads and writes kept apart.
- A rule simple enough for an architecture test to enforce, so later changes cannot drift from it.
- The same behavior, proven by the existing feature and browser tests running unchanged.

**Non-Goals:**

- Generic infrastructure beyond two buses: no handler registry file, no middleware pipeline, no async dispatch.
- Moving `SpotifyOAuth`, `SpotifyScopes` or the value objects out of `app/Spotify`. They are infrastructure that handlers use.

## Decisions

### D1. Layout and naming

```
app/Bus/Command.php, Query.php          marker interfaces
app/Bus/CommandBus.php, QueryBus.php    concrete buses
app/Bus/HandlerNotFound.php             LogicException
app/Application/UseCaseFailed.php      base class for use-case failures
app/Application/<Context>/Commands/<Name>.php and <Name>Handler.php
app/Application/<Context>/Queries/<Name>.php and <Name>Handler.php
app/Application/<Context>/Exceptions/<Name>.php
app/Application/<Context>/Results/<Name>.php   read objects returned by queries
```

- Contexts follow the features: `Auth`, `Owner` and `Spotify`.
- Commands are verbs in the imperative (`ConnectSpotify`); queries start with `Get` (`GetSpotifySettings`).
- Messages are `final readonly` classes with public promoted properties.
- Password properties carry `#[\SensitiveParameter]`, so they never appear in stack traces.
- The base exception is `UseCaseFailed`, not `DomainException`. PHP already has a global `\DomainException`, which is a `LogicException`, so a `catch (DomainException)` would catch a different class depending on the file's imports.
- Query results live in `Results/`, not `Views/`, so they are not confused with Laravel's Blade views.

*Alternatives:*

- One folder per use case (`Spotify/ConnectSpotify/{Command,Handler}.php`). Rejected: the class names repeat, and the split between commands and queries is less visible at a glance.
- Keeping the classes in `app/Spotify`. Rejected: that folder holds the infrastructure the handlers use.

### D2. Two concrete buses, resolved by convention

- `CommandBus::dispatch(Command $command): void` and `QueryBus::ask(Query $query): mixed` both build the handler class name as `$message::class.'Handler'`.
- Each bus receives `Illuminate\Contracts\Container\Container` in its constructor. It resolves the handler from that container on every call, so constructor injection works, and calls `handle($message)`. Because the container is injected, the unit tests can hand the buses a plain `new Container()`: the Unit suite does not boot Laravel.
- A missing handler throws `HandlerNotFound`. Its message names only the message class, never the payload.
- `Query` carries the phpdoc template `@template TResult`, and `ask()` documents `@return TResult`, so editors can infer the result type.
- The marker interfaces exist for type safety and for the architecture test. The ban on interfaces applies only to repositories.

*Alternatives:*

- Laravel's `Bus::dispatchSync` with `Bus::map`. Rejected: it does not tell a command from a query, and the map is one more file to keep in sync.
- Injecting the handler directly into the entry point. Rejected: the owner chose buses, and they make every entry point look the same.

### D3. Commands return nothing; failures are domain exceptions

- A command handler's `handle()` returns `void`. Success is the absence of an exception.
- Failures extend `App\Application\UseCaseFailed`, a `RuntimeException`. Their messages are fixed texts with no user data, tokens or response bodies.
- Entry points catch the specific exception and map it to today's output:
  - for login, the `ValidationException` with `auth.failed` or `auth.throttle`;
  - for Spotify, the flashed `spotifyMessage` from `ConnectFailure`;
  - for the console, the error line.
- `SpotifyConnectionFailed` carries the `ConnectFailure` reason, so the message texts and tones stay where they are.

*Alternative:* commands that return a small result, such as `?ConnectFailure`. Rejected by the owner in favour of strict CQRS.

### D4. Queries return plain data, never models

- A query returns a read object from `Results/`, an enum, a scalar or null.
- Entry points never receive an Eloquent model, so they cannot write through one.
- The architecture test rejects a query handler whose return type is a `Model`.

### D5. Inventory of use cases

| Entry point | Today | After |
|---|---|---|
| `POST /login` | `LoginRequest::authenticate()` | `LogInOwner(email, password, remember, ip)`. The rate limit, `Auth::attempt`, the hit and the clear move to the handler. It throws `InvalidCredentials` or `TooManyLoginAttempts(seconds)`. The controller fires `Lockout($request)` on the second, maps both to the same validation errors, then regenerates the session. |
| `POST /logout` | `Auth::guard('web')->logout()` in the controller | `LogOutOwner`. The handler logs the guard out. The controller keeps the session invalidation, the token regeneration and `Inertia::clearHistory()`, which are HTTP concerns. |
| `GET /login` | renders with the session status | unchanged: no application data is read |
| `app:create-owner` | repository calls | `GetOwnerAccount` returns `?OwnerAccount(email)` for the confirmation prompt. Then `CreateOwnerAccount(email, password)` throws `OwnerAccountExists`, or `ReplaceOwnerAccount(email, password)` throws `OwnerAccountMissing`. The interactive prompts and input validation stay in the console command. |
| `GET /spotify/connect` | state and URL in the controller | The controller still creates the state and stores it in the session. `GetSpotifyAuthorizationUrl(state)` returns the URL or throws `SpotifyNotConfigured`, and the controller then redirects to the settings page as today. |
| `GET /spotify/callback` | the whole flow in the controller | The controller keeps the session state check and the mapping of the `error` and missing `code` parameters to `ConnectFailure`, before any request to Spotify. Then `ConnectSpotify(userId, code)`: exchange, account, owner, Premium and store. It throws `SpotifyConnectionFailed(reason)`. |
| `DELETE /spotify/connection` | repository call | `DisconnectSpotify(userId)` |
| `GET /settings/spotify` | repository and OAuth service in the controller | `GetSpotifySettings` returns `SpotifySettings(configured, status, accountName, reconnectBefore, missingScopes)`. The controller adds the flashed message from the session. |
| shared `spotify` props | `app(SpotifyConnectionRepository)` | `GetSpotifyStatus` returns `?SpotifyConnectionStatus`. Guests still skip the query. |
| dashboard, styleguide, `/` | closures that render or redirect | unchanged (proposal, Non-goals) |

The state check stays in the controller on purpose. It guards the session, must run before anything else, and needs no application data.

### D6. Repositories

- Handlers are the only callers of repositories.
- `SpotifyConnectionRepository::storeForOwner` and `deleteForOwner` take `int $userId`, because commands carry ids and not models.
- `UserRepository` keeps its signatures: `ReplaceOwnerAccountHandler` loads the owner itself with `findOwner()`.
- Query handlers call only reading methods. This is a convention checked in review, because the owner chose not to split repositories.

### D7. Enforcement

Arch tests live in `tests/Unit/ArchitectureTest.php`:

- `App\Http\Controllers`, `App\Http\Middleware`, `App\Http\Requests` and `App\Console` must not use `App\Repositories`, `App\Models`, `App\Spotify\SpotifyOAuth` or the `DB`, `Auth` and `RateLimiter` facades.
- `App\Application` must not use `Illuminate\Http\Request`, `Inertia` or `App\Http`.
- `App\Bus` and the messages are final.

A reflection test walks `app/Application`:

- every class under `Commands` that is not a handler implements `Command`, is `final readonly`, and has a `<Name>Handler` whose `handle()` takes it and returns `void`;
- the same holds under `Queries` with `Query`, except that `handle()` must not return `void` or a `Model`;
- every handler has its message;
- every class under `Exceptions` extends `UseCaseFailed`.

The entry-point expectation grows during the migration. It starts in step 2.1 with `App\Console`, the first entry point migrated, because an empty list would check nothing. Each later group adds its entry points, and step 4.3 replaces the list with the four namespaces.

New unit tests in `tests/Unit/Bus` check that the buses resolve a handler, that `ask()` returns the handler's result, and that a missing handler throws `HandlerNotFound` without the payload in its message. Pest declares the classes in a test file in the global namespace, so the test-only messages and handlers get names unique to the bus tests, for example `BusTestPing` and `BusTestPingHandler`.

### D8. Documentation

- AGENTS.md, under Code conventions, gets a "CQRS" rule next to the repository rule. The repository rule is rewritten so the two agree. Repositories are injected only into handlers, and entry points, service providers and validation rules never query models. The `unique` and `exists` rules stay exempt as queries Laravel runs internally.
- The `context:` block in `openspec/config.yaml` gets one line.
- The README, under Conventions, gets one bullet.
- The rule text matches across all three, so later proposals and agents pick it up.

## Risks / Trade-offs

- [Behavior drift while moving login or the callback] → The existing feature and browser tests are the safety net and must pass unchanged. The steps move one entry point at a time and run its tests.
- [Login messages or the `Lockout` event change subtly] → The exceptions map to the same translation keys. The controller fires `Lockout` with the real request. `OwnerAuthTest` covers both.
- [Secrets in exceptions or traces] → Exception messages are fixed texts, password properties use `#[\SensitiveParameter]`, and the buses do no logging.
- [More files for small features] → Accepted. It is the owner's choice, and the layout keeps each use case in two short classes.
- [Query handlers calling a write method] → Not machine-checked (D6). The review checklist and the AGENTS.md rule cover it.
- [Helpers slip past the arch test] → The arch test sees class references, not helper functions such as `auth()`, `app('db')` or `request()`. An entry point could still reach data through them. The AGENTS.md rule names them, and review checks for them.

## Migration Plan

This is a pure refactor with no data migration. Deploy as usual. Rollback means reverting the commit.

## Backlog follow-ups (applied at /ship)

- **T-05:** `spotify-api-client` follows CQRS. The shared client is infrastructure in `app/Spotify` that handlers use. Token refresh happens inside handlers, never in controllers.
- **T-06:** `GET /spotify/token` is a query, `GetSpotifyPlaybackToken`. Because it may refresh the token as it reads, its design must say whether the refresh happens through the client inside the query handler or through a separate command.
