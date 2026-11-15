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

The Spotify client keeps two things in the cache: the lock that stops parallel requests from refreshing the token twice, and the pause every request respects after Spotify's rate limit (429). The cache store must therefore be shared by every PHP process: keep `CACHE_STORE=database` (or Redis), never `array` outside the tests.

The tokens are encrypted with `APP_KEY`. To rotate the key without losing them, put the old key in `APP_PREVIOUS_KEYS`. If the key is lost, the tokens can no longer be read: the settings page then asks you to reconnect Spotify.

Manual check with the real Spotify app, after changing the connection code:

- connect, and check that the page shows "Spotify connected", your name and "Reconnect before <date>";
- check that the tokens are not in the page source;
- disconnect;
- connect again.

Never commit real ids, secrets or tokens: they live only in `.env`.

## YouTube Music

AeroPlayer connects the owner's YouTube Music account with Google's device flow and an OAuth client of your own. It asks only for read-only access (`youtube.readonly`). Create the client once:

1. In the [Google Cloud console](https://console.cloud.google.com/), create a project and enable the **YouTube Data API v3**.
2. Configure the OAuth consent screen (Google Auth Platform) for external users, and add your own Google account under **Test users**.
3. Create an OAuth client of type **TV and Limited Input devices**.
4. Fill in the three variables in `.env`:
   - `YOUTUBE_MUSIC_CLIENT_ID` and `YOUTUBE_MUSIC_CLIENT_SECRET`, from the client;
   - `YOUTUBE_MUSIC_OWNER_CHANNEL_ID`, the id of your YouTube channel (it starts with `UC`). Find it under **Settings → Advanced settings** on YouTube, or as the `id` that `GET https://www.googleapis.com/youtube/v3/channels?part=snippet&mine=true` returns.

   Without any of the three, the settings page shows "YouTube Music is not configured."
5. Sign in, open **Settings** and switch to **YouTube Music** in the settings link bar (`/settings/youtube-music`), then press **Connect YouTube Music**. The page shows a code: open the address it links to, enter the code and approve with your account. Google warns that the app is not verified, because it is your own: choose "Advanced" and continue. The page notices the approval by itself. Only the account whose channel is `YOUTUBE_MUSIC_OWNER_CHANNEL_ID` is accepted.

While the consent screen stays in **Testing**, Google ends the authorization after 7 days, and you have to connect again. Publishing the app to production requires Google's verification. Disconnecting tries to revoke the access at Google and deletes the stored tokens; you can check at [myaccount.google.com/permissions](https://myaccount.google.com/permissions) that AeroPlayer no longer has access.

The attempt waiting for your approval, with its encrypted device code, lives in the cache, and a lock stops two status requests from asking Google at once. The cache store must be shared by every PHP process, as for Spotify.

Google's access tokens last about an hour, and the app renews them by itself before they expire, one renewal at a time. When Google refuses a renewal because the authorization ended, for example after the 7 days in **Testing**, the settings page asks you to reconnect YouTube Music. When Google refuses the client instead, for example after the client secret changed, the page shows "YouTube Music is not configured correctly." until you fix the variables in `.env`: reconnecting would not help, and the next good renewal clears the message.

Manual check with the real Google client, after changing the connection code:

- connect, approve the code with your account, and check that the page shows "YouTube Music connected" and your channel name;
- check that the page source holds no token or device code;
- disconnect, and check that the access is gone from myaccount.google.com/permissions;
- connect again.

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
- Single-action controllers: one invokable controller per route (only `__invoke` is public), grouped by context in `app/Http/Controllers/<Context>` and named after the action (`Show…Controller` for a page). The route files hold no closures, only the declarative `Route::redirect`. `tests/Unit/ArchitectureTest.php` and `tests/Feature/RoutesTest.php` enforce it.
- Every model query in `app/` goes through a concrete repository in `app/Repositories/`, injected only into handlers. The starter kit's auth code goes through `App\Repositories\UserRepository` too, so there is no exception left.
