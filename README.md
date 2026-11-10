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
- Every model query in `app/` goes through a concrete repository in `app/Repositories/`. The starter kit's auth code goes through `App\Repositories\UserRepository` too, so there is no exception left.
