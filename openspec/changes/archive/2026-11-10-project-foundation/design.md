# Design

## Context

- The repository is not empty: it holds `backlog/`, `openspec/`, `templates/`, `.claude/`, `AGENTS.md`, `CLAUDE.md`, `workflow.yaml` and a `.gitignore`. The Laravel app must live at the **root** next to them, because T-04 (deploy) and T-20 (Docker) assume a root-level Laravel project.
- Local tools on the owner's machine: PHP 8.5 (CLI), Composer 2.10, Node 24 / npm 11, Docker 27. The Laravel installer (`laravel new`) is not installed.
- Hostinger (T-04): the `illodosadev.io` site runs PHP 8.3 until the owner confirms the move to 8.4 (D2). The `music` subdomain may share it.
- Repository rule (AGENTS.md): every model query in `app/` goes through a concrete repository class. The starter kit's code doesn't follow it.
- See `proposal.md` for the motivation, and `specs/app-foundation` and `specs/error-pages` for the required behavior.

## Goals / Non-Goals

**Goals:**
- A root-level Laravel app from the official Vue starter kit, unchanged except for test tooling, the PHP platform pin, the error tests and the docs.
- Dependencies that are really resolved for PHP 8.4, verified on PHP 8.4, not only on the local 8.5.
- Error-leak tests that keep holding when later tasks change routes (T-02 removes `Welcome`; D11).

**Non-Goals:**
- Changing the starter kit's auth, layouts or components (T-02, T-03, T-25), including bringing its auth code under the repository rule (T-02, see D8).
- CI workflows (T-04) and Docker images (T-20).

## Decisions

### D1. Create the kit without installing into a temporary folder, then move it into the root
`composer create-project laravel/vue-starter-kit <tmp> --no-install --no-scripts`, then move every file into the repo root. `.gitignore` is merged (keep the template's entries and add the kit's). Template files are never overwritten: on a name clash, the template's file wins and the kit's content is merged by hand.
- The kit's post-create steps are then run by hand, after D2 resolves dependencies: copy `.env.example` to `.env`, `php artisan key:generate`, create `database/database.sqlite`, `php artisan migrate`.
- *Alternative: `laravel new`.* Rejected: it needs the installer, which isn't installed, and an empty target folder.
- *Alternative: app in a subfolder (`app/` or `src/`).* Rejected: T-04 and T-20 assume a root-level project, and Laravel tooling (Pest, Vite, Ziggy) expects it.
- *Alternative: plain `create-project` (with install).* Rejected: it resolves dependencies for the local PHP 8.5 before the pin exists, and a later `composer update --lock` does not re-resolve them.

### D2. Latest stable kit and Laravel major, with dependencies resolved for PHP 8.4
Set `"config": {"platform": {"php": "8.4.0"}}` and `"require": {"php": "^8.4"}` in `composer.json`, then run a **full** `composer update`. Composer then resolves every package for PHP 8.4 even when it runs on 8.5. (Amended 2026-11-10: first pinned to 8.3, when the Hostinger site was on 8.3; the owner chose 8.4 and the site follows.)
- **Verification on real 8.4:** in a `php:8.4-cli` container, run `composer check-platform-reqs --lock`, `composer install` and `php artisan test`. The official image has no Composer, `git` or `unzip`, so the container gets `git` and `unzip` with `apt-get`, Composer copied from the `composer:2` image (`/usr/bin/composer`), and `pdo_mysql` via `docker-php-ext-install`. Running Composer *from* the `composer:2` image instead would use its newer PHP and defeat the check. Running `check-platform-reqs` locally on 8.5 checks 8.5, not the pin, so it proves nothing.
- *Alternative: PHP 8.3, the server's current version.* Rejected by the owner on 2026-11-10: the project moves to 8.4, and the Hostinger site follows after a separate confirmation (T-04).
- If the latest Laravel major requires more than PHP 8.4 at install time, install the latest major that supports 8.4 and record it in the README.

### D3. Pest as the test runner
Use the kit's Pest setup if it ships one. If it ships PHPUnit, add `pestphp/pest` and `pestphp/pest-plugin-laravel` and convert the kit's tests with `pest --init` and Pest Drift, so that `php artisan test` runs Pest.
- *Alternative: keep PHPUnit.* Rejected: the owner chose Pest (T-01), and T-03 needs Pest 4 browser testing.
- **Amended while implementing (2026-11-10):** the kit ships PHPUnit only, and `tests/Pest.php` was an unused Pest config. Pest 4 (`pestphp/pest`, `pestphp/pest-plugin-laravel`, `pestphp/pest-plugin-drift`, all `^4.0`) was added and the kit's tests converted with Drift. Pest 4 needs PHPUnit 12, so PHPUnit moves from 11.5 to 12.5. Pest 3 cannot coexist with the kit's locked PHPUnit 11.5, and Pest 5 requires PHP 8.4.

### D4. Tests use in-memory SQLite and don't need built assets
- Keep the kit's `phpunit.xml` settings: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, array session and cache.
- Call `$this->withoutVite()` in the base `Tests\TestCase::setUp()`, so Inertia pages render in tests without `public/build/manifest.json`. That makes the suite runnable in the Node-less PHP 8.4 container (D2) and in any order relative to the build.
- *Alternative: build before testing everywhere.* Rejected: it couples the PHP test run to Node, and the 8.4 verification container would need Node or a mounted build.
- Browser tests (T-03) will need a real build; that is their concern.

### D5. Error-leak tests
In `tests/Feature/ErrorPagesTest.php`, with `config(['app.debug' => false])` in every test:
- **404:** request `/route-that-does-not-exist`.
- **500:** register `Route::get('/__test/boom', fn () => throw new RuntimeException('boom-secret-1234'))` inside the test. No test route ships in the app.
- **Unreachable database:**
  - Add a connection named `unreachable`, used only in this test: the `mysql` driver, `host=db.invalid` (`.invalid` never resolves, per RFC 6761), `password=db-secret-5678` and a 1-second PDO timeout.
  - Set `session.driver=database` and `session.connection=unreachable`. **`database.default` stays on SQLite**, so `RefreshDatabase` still rolls back cleanly at teardown. The connection is purged (`DB::purge('unreachable')`) after the test.
  - The test first asserts `extension_loaded('pdo_mysql')`, so a missing driver ("could not find driver") can't make it pass for the wrong reason.
  - It requests `/login`, a page that exists before and after T-02, so the test stays valid after D11.
- **Shared leak assertion:** the response must not contain `base_path()`, `storage_path()`, "Stack trace", `#0 ` or the secret string, and for the database test also not `db.invalid`.
- *Alternative: switch `database.default` to MySQL.* Rejected: `RefreshDatabase`'s teardown would then hit the unreachable host and fail the test after it has passed.
- *Alternative: request "/" for the database test.* Rejected: D11 turns "/" into a redirect, which may answer without touching the session.

### D6. Frontend build; no type check in this change
`npm ci`, then `npm run build`, produces `public/build/manifest.json`. The build needs no PHP and no generated route helpers (verified 2026-11-10).
- **No type check in this change (decided 2026-11-10).** The kit has no type-check script, and running `vue-tsc` on it reports about 20 errors: two `tsconfig.json` `types` entries don't resolve, and props such as `auth` are typed `unknown` in `AppHeader.vue`. Fixing them means changing starter-kit source, which this change keeps unchanged. The gap is recorded in Risks and handed to T-03 and T-02.
- The kit's route helper is **Ziggy** (`tightenco/ziggy`, generated by `php artisan ziggy:generate` in its CI), not Wayfinder.

### D7. Docs
- `openspec/config.yaml` `context:`: replace "Planned stack (to be confirmed by T-01)" with the installed versions (Laravel major, Inertia, Vue 3 + TS, Tailwind major, Pest, Fortify), the PHP 8.4 platform, the `en` locale and the test commands.
- `AGENTS.md`, "Stack" section: replace the "stack-agnostic template" note with a short summary of the same stack and a pointer to `openspec/config.yaml`, as that section asks when the template is instantiated.
- New `README.md`: what AeroPlayer is (one line), requirements, setup (`composer install`, `.env` from `.env.example`, `php artisan key:generate`, `npm ci`), `npm run build` and `php artisan test`. It also points to `backlog/` and `openspec/` for the workflow.

### D8. Temporary exemption from the repository rule for unchanged starter-kit code
The kit's auth and settings code queries and writes models directly (for example `CreateNewUser` with `User::create` and a `unique` rule on `User`, and the settings controllers). This change leaves that code **unchanged**, under an explicit, temporary exemption from the AGENTS.md repository rule. The exemption is recorded in AGENTS.md "Code conventions", where agents read the rule, and in the README.
- T-02 reworks that code (removing most of it) and introduces `App\Repositories\UserRepository` for what remains. The exemption ends with T-02.
- Code written in this change touches no models, so it needs no repository.
- *Alternative: refactor the kit's auth onto repositories now.* Rejected: it is T-02's scope, and most of that code is deleted there.

## Risks / Trade-offs

- **[Local PHP 8.5 hides 8.4+ syntax or dependency drift]** → D2's verification on a real `php:8.4-cli` (task 1.4 and the final run in 4.3). T-04's CI then runs on the server's version.
- **[The kit's type errors stay until someone fixes them]** No type check runs in this change, so ~20 errors in starter-kit code remain. → Recorded for T-03 (the components it touches) and T-02 (auth props), which must add a type check when they change those files.
- **[The kit changes between this proposal and the install]** For example its test runner or script names. → D3 and D6 handle both cases, and versions are recorded in `openspec/config.yaml`.
- **[The kit's default auth is fully open (registration, etc.) and outside the repository rule until T-02]** → Nothing is deployed before T-02 (T-04 depends on it). D8 records the exemption and when it ends.
- **[`withoutVite()` hides a broken asset reference in tests]** → The production build is checked on its own (task 2.3), and T-03's browser tests run against a real build.
- **[The database test is slow if DNS doesn't fail fast]** → The `.invalid` TLD plus a 1-second timeout. If it still exceeds about 3 s locally, the session connection's PDO factory can be swapped to throw a connection `PDOException` instead.
- **[The backlog still says PHP 8.4, and T-02 doesn't know it ends D8]** → Task 4.4 updates T-01, T-02, T-04 and T-20, in Spanish like those tasks.
- **[The error test needs `pdo_mysql`]** The suite fails wherever that extension is off, including a local PHP without it. → The README lists `pdo_mysql` and `pdo_sqlite` as requirements, and task 3.3 checks the local PHP first.
- **[T-03's browser tests need a built frontend]** They will break "Self-contained automated test suite" as written. → The proposal records that T-03 MODIFIES it.

## Migration Plan

Not applicable: this is the first application code and nothing is deployed. To roll back, revert the commits.

## Open Questions

- The exact Laravel and starter-kit versions, which depend on what is current at install time. They are recorded in `openspec/config.yaml` and don't change specs or tasks.
