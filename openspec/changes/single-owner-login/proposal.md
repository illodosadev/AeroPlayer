# Proposal

Implements backlog task T-02 (login de un solo usuario).

## Why

AeroPlayer will be reachable from the Internet and will store Spotify and YouTube Music credentials, so every page must sit behind a login. The discovery fixes a single user with no sign-up. The starter kit ships registration, password reset, email verification, password confirmation and profile, password and appearance settings that this app does not need, and each one is attack surface and maintenance cost. Removing them also means the app never sends email.

## What Changes

- **BREAKING:** the owner account is created only with `php artisan app:create-owner`, an interactive console command. Registration is removed.
- Authentication is reduced to login and logout with email and password. The email is case-insensitive at login. Sign-up, password reset, email verification and password confirmation are removed from the backend and the frontend, and their routes return 404.
- All settings pages are removed (profile with account deletion, password, appearance), including the "Settings" entry of the user menu. T-05 brings back the first settings page.
- Guests opening "/" are redirected to "/login"; signed-in owners go to "/dashboard" (the starter kit page for now). The Welcome page is removed.
- Logout redirects to "/login", requires POST with a valid CSRF token, and clears the browser navigation history so "back" shows no protected page.
- Changing the owner password through the command ends every other session.
- A `UserRepository` is added for the remaining user queries, which ends the temporary repository exemption in AGENTS.md.

## Capabilities

### New Capabilities

- `owner-auth`: the single owner's login, logout, rate limiting, "Remember me", the removed auth features, and the `app:create-owner` command that creates or replaces the owner account.

### Modified Capabilities

- `app-foundation`: the requirement "Root page renders the Welcome page component" is replaced by "Root URL redirects by session state".

## Non-goals

- Login page styling (T-03).
- Type checking of the frontend and the starter kit's TypeScript errors (T-03, which rewrites those components).
- Automated browser tests: the three browser scenarios are checked by hand here; T-03 adds the browser test plugin.
- Fixing the app to light mode and removing the theme code (T-03).
- Running the command on the server and console access on Hostinger (T-04).
- Multi-user accounts, roles, social login, two-factor authentication, mail delivery.
- Choosing the post-login home screen (T-25 moves it to "/library").

## Impact

- Routes, auth and settings controllers and requests, and their Vue pages (design D6). `LoginRequest` lowercases the email; logout changes target and clears history.
- New `CreateOwner` command and `UserRepository`.
- Tests for removed features are deleted; new feature tests cover `owner-auth`.
- Docs: `AGENTS.md`, `README.md`, and `openspec/config.yaml`, which wrongly describes the auth as Fortify.
- No new dependency and no migration.
