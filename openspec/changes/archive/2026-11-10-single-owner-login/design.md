# Design

## Context

The installed starter kit (laravel/vue-starter-kit v1.0.2, see project-foundation) does not use Laravel Fortify. Its auth lives in its own controllers under `app/Http/Controllers/Auth`, its routes in `routes/auth.php`, and login validation and rate limiting in `app/Http/Requests/Auth/LoginRequest.php`. It has no two-factor or passkey code and no `config/fortify.php`. The backlog task T-02 and `openspec/config.yaml` describe Fortify features; this design follows the installed code instead, and `openspec/config.yaml` is corrected in tasks.md.

Current kit behaviour that this change keeps or removes:

- Login (`AuthenticatedSessionController@store`) validates with `LoginRequest`, which allows 5 failed attempts per email and IP and then redirects back with an error on `email`. It regenerates the session on success and honours `remember`. It passes the email to `Auth::attempt` as typed.
- Logout (`destroy`) invalidates the session, regenerates the CSRF token and redirects to "/".
- `/` renders the `Welcome` page; `/dashboard` requires `auth` and `verified`.
- Registration, password reset, email verification, password confirmation and the settings pages (profile with account deletion, password, appearance) each have a controller, a route and a Vue page.
- The default users migration already creates the `sessions` table that `SESSION_DRIVER=database` (`.env.example`) uses. The `User` model casts `password` to `hashed`.

## Goals / Non-Goals

**Goals**

- Only login and logout remain for accounts. The removed routes return 404.
- The owner account is created and replaced only by `php artisan app:create-owner`.
- Changing the password through the command ends every other session of the owner.
- Going back in the browser after logout shows no protected page.
- User queries in `app/` go through `App\Repositories\UserRepository`, which ends the temporary exemption in AGENTS.md.

**Non-Goals**

- Styling, light-mode-only and type checking (T-03); deployment (T-04); settings pages that come back later (T-05, T-18). See the proposal.

## Decisions

### D1. Keep the kit's auth; remove features by deleting their routes and code

Fortify is not installed, so nothing is switched off in config. Each removed feature is deleted: its routes, its controller, its Form Request, its Vue page and the links to it. The kit's tests for the removed features are deleted, not skipped.

*Alternative rejected:* install Fortify and switch features off. That adds a dependency and a second auth stack only to remove what the kit already provides.

### D2. Case-insensitive email at login

`LoginRequest::prepareForValidation()` lowercases and trims `email` before validation, so `Auth::attempt` looks up the stored lowercase email (the command always stores it lowercase, D5). This makes the behaviour the same on SQLite, whose `=` comparison is case-sensitive, and on MySQL. The throttle key already lowercases the email, so the rate limit is unchanged.

*Alternative rejected:* a case-insensitive collation or `LOWER(email)` lookup. It depends on the database and needs a custom user provider.

### D3. Rate limit: keep the kit's behaviour

The kit's `LoginRequest` blocks further attempts with a validation redirect to `/login` and the "Too many login attempts" message, not with status 429. The spec follows that. The window opens with the first failed attempt and lasts 60 seconds (the limiter's default decay), so the "Limit lifts" scenario waits 61 seconds after the fifth attempt. No `throttle` middleware is added. Tests call `$this->from('/login')` before POST so the redirect target is "/login".

### D4. Root, dashboard and logout

- `/` becomes a route named `home` that redirects a signed-in owner to `dashboard` and any visitor to `login`. The name stays because the auth layouts link to `route('home')`. `Welcome.vue` is deleted.
- `dashboard` drops the `verified` middleware. Email verification is removed, and `User` does not implement `MustVerifyEmail`.
- `email_verified_at` stays in the users table from the default migration, and the command does not set it, because nothing reads it (confirmed by the owner on 2026-11-10). This replaces T-02's proposed rule of setting it.
- `AuthenticatedSessionController@destroy` redirects to `/login` and calls `Inertia::clearHistory()` after invalidating the session (D8).

### D5. `UserRepository` and the command

`App\Repositories\UserRepository` (concrete class, no interface) exposes:

- `findOwner(): ?User` reads the single account.
- `createOwner(string $email, string $password): User` creates it. The name is the local part of the email. The `hashed` cast hashes the password.
- `replaceOwner(User $owner, string $email, string $password): User` updates the email and password, sets a new `remember_token`, and deletes that user's rows in `sessions`, in one transaction. The `sessions` delete is a query builder call inside the repository, so it follows the repository rule.

`app:create-owner` (`app/Console/Commands/CreateOwner.php`):

1. If `! $this->input->isInteractive()`, print "This command needs an interactive terminal." and return FAILURE. Nothing is read or written. In the installed Symfony Console, the input is non-interactive only with `--no-interaction` (or a negative `SHELL_VERBOSITY`); it does not check whether stdin is a terminal. Piped stdin is therefore accepted: it is a deliberate act, and the password still stays out of shell history and the process list. No `stream_isatty()` check is added, because it would also need a bypass for tests (decided by the owner on 2026-11-10).
2. If an owner exists, ask `confirm("An owner account already exists (<email>). Replace its email and password?")`. On "no", print "Nothing changed." and return FAILURE.
3. Ask the email with `ask`, lowercase and trim it, and validate it with `Validator` (`required`, `email`), looping until valid. Print each error and ask again.
4. Ask the password and its confirmation with `secret`. Validate with `Password::min(12)->mixedCase()->numbers()->symbols()` and `confirmed`, looping the same way. The rules are fixed and do not use `Password::defaults()`, so they do not depend on the environment, and `uncompromised()` is left out because it needs outgoing HTTP (T-02).
5. Call `createOwner` or `replaceOwner`, print "Owner account created for <email>." or "Owner account updated for <email>." and return SUCCESS.

*Why our own Validator loop and not Prompts' `validate:`:* Prompts' `required` prints "Required." instead of the English validation message the scenarios expect, and in tests Prompts may throw instead of asking again (T-02). Laravel's default English messages already match the scenarios, so no language file is added. A test per message confirms this.

*No `--email` or `--password` options* (T-02): a password on the command line stays in shell history and the process list.

### D6. What is removed, and what is not in this version

Removed:

- Controllers: `RegisteredUserController`, `PasswordResetLinkController`, `NewPasswordController`, `VerifyEmailController`, `EmailVerificationPromptController`, `EmailVerificationNotificationController`, `ConfirmablePasswordController`, `Settings\ProfileController`, `Settings\PasswordController`.
- Requests: `app/Http/Requests/Settings/ProfileUpdateRequest.php`.
- Routes: their entries in `routes/auth.php`, and `routes/settings.php` with its `require` in `routes/web.php`, so `/settings` answers 404.
- Vue: the `Welcome`, `Register`, `ForgotPassword`, `ResetPassword`, `VerifyEmail` and `ConfirmPassword` pages, the `settings/*` pages and layout, `components/DeleteUser.vue` and `components/AppearanceTabs.vue` (only used by removed pages), the "Settings" link in `UserMenuContent.vue` and the "Sign up" link in `Login.vue`.
- Tests: `tests/Feature/Auth/RegistrationTest.php`, `PasswordResetTest.php`, `EmailVerificationTest.php`, `PasswordConfirmationTest.php`, `tests/Feature/Settings/*` and `tests/Feature/ExampleTest.php` (it expects 200 on "/").

Kept and adapted: `AuthenticationTest.php` (its logout test now expects "/login") and `DashboardTest.php`.

Kept on purpose: `composables/useAppearance.ts` and its `initializeTheme()` call in `app.ts`. T-03 fixes the app to light mode and removes them.

Not present in v1.0.2 and therefore not removed: two-factor (no trait, columns or routes), passkeys, and `Password::defaults()` configuration in a service provider. The "No two-factor routes" scenario still guards against them.

### D7. Login page

`Login.vue` loses the "Sign up" link. The "Forgot password?" link is already behind `canResetPassword`, which becomes false because the route is gone; the link is deleted as well so no dead `route('password.request')` call remains. The "Remember me" checkbox stays, unchecked by default.

### D8. History encryption on logout

Inertia 2 can encrypt each page's state in the browser history and clear the key on demand. The web middleware group turns encryption on (`Inertia\Middleware\EncryptHistory`, or `Inertia::encryptHistory()` in `HandleInertiaRequests`, whichever the installed version offers), and logout calls `Inertia::clearHistory()`. That call only stores a flag in the session, which the next Inertia response reads, so it MUST come after `$request->session()->invalidate()` and `regenerateToken()`: called before, `invalidate()` flushes the flag and nothing is cleared, with no error. The `/login` response that follows the logout then carries `clearHistory: true`. After logout, "back" cannot decrypt the previous page, so Inertia requests it again and the `auth` middleware redirects to `/login`.

Encryption uses the browser's `crypto.subtle`, which works only in a secure context: HTTPS, or `http://localhost`. Production is HTTPS (T-04); the local stack must be opened on `localhost` (T-20).

*Alternative rejected:* `Cache-Control: no-store` headers. They do not affect Inertia's client-side history state.

### D9. Browser scenarios are checked by hand

Three scenarios need a rendered page: "Login page shows no sign-up or password reset link", "User menu links lead to existing pages only" and "Going back after logout shows no protected page". The browser test plugin arrives with T-03, so here they are checked by hand on the built app, and the result is written in tasks.md under the step that checks them. T-03 turns them into browser tests.

### D10. Testing notes

- **CSRF:** Laravel skips CSRF checks while running unit tests (`VerifyCsrfToken::runningUnitTests()`). The 419 logout test binds, for that test only, a subclass of `Illuminate\Foundation\Http\Middleware\ValidateCsrfToken` whose `runningUnitTests()` returns `false`.
- **Green suite per step:** each step that deletes a feature also deletes the kit tests for it, so `php artisan test` passes after every commit.
- **Sessions on two devices:** tests that need real sessions set `config(['session.driver' => 'database'])`; the `sessions` table comes from the default migration and `RefreshDatabase`.

## Risks / Trade-offs

- If a default validation message in the installed Laravel differs from the scenarios, the scenario is updated, not the message.
- Deleting session rows only works with the `database` driver. The spec scenario uses that driver; other drivers still end "Remember me" through the new token.
- History encryption fails outside a secure context (D8). Opening the local app on a LAN IP over plain HTTP would break navigation.
- Manual checks (D9) can regress unnoticed until T-03 automates them.

## Resolved questions

- `/confirm-password` is removed (D6). Confirmed by the owner on 2026-11-10.
- The command does not set `email_verified_at` (D4). Confirmed by the owner on 2026-11-10.
- History encryption is part of this change (D8); type checking and the theme code go to T-03; the browser scenarios are checked by hand (D9). Decided by the owner on 2026-11-10.

## Backlog follow-ups (applied at /ship)

- T-20: no mail is needed (keep the `log` mailer, no Mailpit); `docker compose exec app php artisan app:create-owner`; open the app on `localhost` (D8).
- T-04: run `php artisan app:create-owner` once over SSH after the first deploy.
- T-03: the login page has no "Sign up" or "Forgot password?"; no settings page remains; automate the three D9 scenarios; add type checking; remove `useAppearance`.
- T-05: `/settings/spotify` brings back the "Settings" link in the user menu and decides where `/settings` goes.
- T-02: add a note that its acceptance criteria were refined by this change (no Fortify, lockout by redirect instead of 429, no `canRegister` prop, "/" redirects instead of Welcome, logout to "/login"); the specs in `openspec/specs/owner-auth` are the reference.
- Discovery (Seguridad / acceso): login by email and password, account created with an artisan command, no outgoing mail.
- The session cookie keeps Laravel's default `SameSite=Lax`, which the Spotify OAuth return (T-05) needs; nothing changes here.
