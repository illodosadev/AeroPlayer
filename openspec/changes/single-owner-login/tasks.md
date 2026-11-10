# Tasks

> Backlog task: **T-02**. Requirements: `specs/owner-auth`, `specs/app-foundation` (delta). Approach: `design.md`.

## 1. Repository

- [x] 1.1 Add `App\Repositories\UserRepository` with `findOwner`, `createOwner` and `replaceOwner` (design D5), as a concrete class with no interface; verify with a Pest test in `tests/Feature` that `replaceOwner` changes the password hash, changes `remember_token` and deletes that user's `sessions` rows while another user's rows stay

## 2. The owner command

- [x] 2.1 Create `app:create-owner` (`app/Console/Commands/CreateOwner.php`) with the non-interactive guard, the existing-owner confirmation and the Validator loop for email and password (design D5); verify the scenarios "Create the owner interactively" and "Email is stored in lowercase" pass with `$this->artisan(...)->expectsQuestion(...)` tests
- [x] 2.2 Cover "Invalid answer is asked again" and "Password rules" with one test per invalid answer from T-02 (invalid email, empty email, 8 characters, no uppercase, no symbol, no number, mismatched confirmation), each expecting the exact English message; verify they pass
- [x] 2.3 Cover "Confirmed replacement" (with `config(['session.driver' => 'database'])` and a second device holding a `remember_web_*` cookie) and "Declined replacement" (design D5); verify both pass
- [x] 2.4 Cover "Non-interactive run does nothing" with `--no-interaction` for an empty table and for an existing owner; verify the exit code is non-zero and the users table is unchanged

## 3. Backend routes and login

- [x] 3.1 Delete the routes, controllers and request listed in design D6 (`routes/auth.php` entries, `routes/settings.php` and its `require`), together with the kit tests for those features listed in D6 (design D10); verify `php artisan test` passes and the scenarios "Removed routes return 404", "Registration attempt creates no user" and "No two-factor routes" pass, with the routes as a Pest dataset and the two-factor check over `Route::getRoutes()`
- [x] 3.2 Replace the `/` closure with the redirect named `home` and remove `verified` from `dashboard` (design D4); verify "Guest opens the root URL", "Signed-in owner opens the root URL" and "Protected page without session" pass
- [x] 3.3 Lowercase and trim the email in `LoginRequest::prepareForValidation()` (design D2); verify "Correct credentials sign in" and "Email case is ignored" pass on SQLite
- [x] 3.4 Change `AuthenticatedSessionController@destroy` to redirect to `/login` and call `Inertia::clearHistory()`, after `invalidate()` and `regenerateToken()`, and turn on history encryption for the web group (design D4, D8); update `AuthenticationTest.php` so its logout test expects "/login"; verify "Logout ends the session" passes, an Inertia response carries `encryptHistory: true` in its page data, and the `/login` page loaded right after logout carries `clearHistory: true`

## 4. Frontend

- [x] 4.1 Delete the Vue pages, layout and components listed in design D6, and remove the "Settings" link from `UserMenuContent.vue` and the "Sign up" and "Forgot password?" links from `Login.vue` (design D7); verify `grep -rnE "route\('(register|password\.|verification\.|profile\.|appearance)" resources/js` returns no match
- [x] 4.2 Build the frontend; verify `npm run build` exits 0 and `public/build/manifest.json` exists (spec app-foundation, "Production frontend build")

## 5. Tests

- [x] 5.1 Check that no kit test for a removed feature is left (deleted in 3.1); verify `grep -rnE "register|password\.request|profile\.(edit|update)|verification\." tests` returns no match
- [x] 5.2 Replace the root test in `tests/Feature/FoundationTest.php` (it asserts the Welcome component) with the two root-redirect scenarios from `specs/app-foundation`; verify the file passes
- [x] 5.3 Add feature tests for the remaining `owner-auth` scenarios: failed login (wrong password, unknown email, empty table, incomplete form), the lockout and its lift (`$this->travel(61)->seconds()`, design D3), "Remember me" (checked, not sent, after session expiry), "Login page while signed in" and "Login page offers no password reset" (`assertInertia`); verify they pass
- [x] 5.4 Add feature tests for logout with GET (405) and without CSRF token (419, binding the `ValidateCsrfToken` subclass from design D10 in that test), and for two devices with `config(['session.driver' => 'database'])`; verify they pass

## 6. Manual browser checks (design D9)

- [ ] 6.1 With the app built and served on `localhost`, check "Login page shows no sign-up or password reset link", "User menu links lead to existing pages only" and "Going back after logout shows no protected page"; write the date and result of each check under this step

## 7. Repository rule and documentation

- [x] 7.1 In `AGENTS.md`, delete the "Temporary exemption until T-02" bullet and change "Only queries that Laravel or Fortify run internally are exempt" to "Only queries that Laravel runs internally are exempt"; verify `grep -n "Fortify\|exemption" AGENTS.md` returns no match
- [x] 7.2 In `openspec/config.yaml` (`context:`), replace "Fortify auth" with "the starter kit's own auth controllers" and remove the Fortify and T-02 exemption wording from the data-access lines; verify `grep -n "Fortify\|until T-02" openspec/config.yaml` returns no match
- [x] 7.3 In `README.md`, add `php artisan app:create-owner` to the setup after `php artisan migrate` and drop the T-02 exemption from Conventions; verify the README lists the command and no longer mentions the exemption

## 8. Verification

- [x] 8.1 Check that `app/` has no model query outside `app/Repositories` with `grep -rn "User::" app --include=*.php | grep -v Repositories`; verify no match other than type references
- [x] 8.2 Run `php artisan test` in the PHP 8.4 container; verify exit code 0
- [x] 8.3 Run `npm ci && npm run build`; verify exit code 0
- [x] 8.4 Run `openspec validate single-owner-login --strict`; verify it passes
