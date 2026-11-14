# AeroPlayer

A private, single-user web music player that plays your Spotify Premium and YouTube Music subscriptions in one Frutiger Aero interface.

## Requirements

- PHP 8.4 or later, with the `pdo_sqlite`, `pdo_mysql` and `sockets` extensions enabled (`sockets` is used by the browser tests)
- Composer 2
- Node 22 or later with npm

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan app:create-owner
npm ci
npx playwright install chromium
```

The last command downloads the headless Chromium the browser tests use.

## Spotify

AeroPlayer plays music through the owner's own Spotify app. Register it once:

1. In the [Spotify Developer Dashboard](https://developer.spotify.com/dashboard), create an app with your own Spotify **Premium** account and select the Web API and the Web Playback SDK. The app stays in development mode, which requires the app owner to have Premium.
2. Add both redirect URIs to the app. They must match exactly, and Spotify does not accept `localhost`:
   - local: `http://127.0.0.1:8000/spotify/callback`;
   - production: `https://music.<domain>/spotify/callback`, once the app is deployed.
3. Fill in the four variables in `.env`:
   - `SPOTIFY_CLIENT_ID` and `SPOTIFY_CLIENT_SECRET`, from the app's settings;
   - `SPOTIFY_OWNER_ID`, your Spotify user id: the last part of your profile link (`https://open.spotify.com/user/<id>`), or the `id` that `GET https://api.spotify.com/v1/me` returns;
   - `SPOTIFY_REDIRECT_URI`, the redirect URI of this environment.

   Without the first three, the settings page shows "Spotify is not configured."
4. Open the app on `http://127.0.0.1:8000`, never `http://localhost:8000`, and set `APP_URL` to the same address. The session cookie of `localhost` does not travel to `127.0.0.1`, so the callback would arrive without a session.
5. Keep `SESSION_SAME_SITE=lax` (the default). With `strict`, the session cookie does not travel with the redirect back from Spotify.
6. Sign in and open **Settings** in the user menu (`/settings/spotify`), then press **Connect Spotify**. Only the account in `SPOTIFY_OWNER_ID` is accepted.

Spotify's refresh tokens last 6 months from the moment you authorize, so the settings page shows "Reconnect before <date>": connect again before then. Disconnecting deletes the stored tokens; to remove AeroPlayer's access on Spotify's side too, use [spotify.com/account/apps](https://www.spotify.com/account/apps/).

Manual check with the real Spotify app, after changing the connection code:

- connect, and check that the page shows "Spotify connected", your name and "Reconnect before <date>";
- check that the tokens are not in the page source;
- disconnect;
- connect again.

Never commit real ids, secrets or tokens: they live only in `.env`.

## Build

```bash
npm run build
```

Writes the production assets and `public/build/manifest.json`.

## Type check

```bash
npm run types
```

Runs `vue-tsc` over the Vue and TypeScript sources.

## Test

```bash
npm run build
php artisan test
```

There are three suites:

- `Unit` and `Feature` run on in-memory SQLite and need neither a built frontend nor a MySQL server: `php artisan test --testsuite=Unit,Feature`. They need `pdo_mysql`, because one test checks that a database failure returns a page without credentials.
- `Browser` (Pest 4 with Playwright and Chromium) opens the real pages, so it needs the production build first: `php artisan test --testsuite=Browser`.

On Windows, `php artisan test` with all three suites at once can stop after the Feature suite; run `vendor/bin/pest` instead, which runs the same tests.

## Look and feel

The app is light mode only and uses its own Frutiger Aero components (`resources/js/components/aero/`) and tokens (`resources/css/aero/`). The signed-in page `/styleguide` shows every component and state. The design rules for Claude Code are in `.claude/skills/frutiger-aero/`.

## Conventions

- Specs and tasks live in `openspec/`, and the backlog lives in `backlog/`. Read `AGENTS.md` before changing anything.
- CQRS: every use case is a Command (changes state, returns nothing, fails with an `App\Application\UseCaseFailed` exception) or a Query (returns a `Results/` object, an enum, a scalar or null, never a model). Each has its Handler in `app/Application/<Context>/{Commands,Queries}`. Controllers, middleware, form requests and console commands only validate, handle HTTP or console concerns and dispatch through `App\Bus\CommandBus` or `App\Bus\QueryBus`. `tests/Unit/ArchitectureTest.php` enforces it.
- Every model query in `app/` goes through a concrete repository in `app/Repositories/`, injected only into handlers. The starter kit's auth code goes through `App\Repositories\UserRepository` too, so there is no exception left.
