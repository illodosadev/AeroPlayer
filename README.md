# AeroPlayer

A private, single-user web music player that plays your Spotify Premium and YouTube Music subscriptions in one Frutiger Aero interface.

## Requirements

- PHP 8.4 or later, with the `pdo_sqlite` and `pdo_mysql` extensions enabled
- Composer 2
- Node 22 or later with npm

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm ci
```

## Build

```bash
npm run build
```

Writes the production assets and `public/build/manifest.json`.

## Test

```bash
php artisan test
```

The suite runs on in-memory SQLite and does not need a built frontend or a MySQL server. It also needs `pdo_mysql`, because one test checks that a database failure returns a page without credentials.

## Conventions

- Specs and tasks live in `openspec/`, and the backlog lives in `backlog/`. Read `AGENTS.md` before changing anything.
- Every model query in `app/` goes through a concrete repository in `app/Repositories/`. The starter kit's auth and settings code is an explicit, temporary exception until T-02 adds `UserRepository`.
