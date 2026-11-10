# Tasks

> Backlog task: **T-01**. Requirements: `specs/app-foundation`, `specs/error-pages`. Approach: `design.md`.

## 1. Install the starter kit at the repository root

- [x] 1.1 Run `composer create-project laravel/vue-starter-kit <tmp> --no-install --no-scripts` into a temporary folder outside the repo and record the kit and Laravel versions from its `composer.json` (design D1, D2); verify the folder has `composer.json`, `package.json` and no `vendor/`
- [x] 1.2 Move the kit into the repo root without overwriting template files (`backlog/`, `openspec/`, `templates/`, `.claude/`, `AGENTS.md`, `CLAUDE.md`, `workflow.yaml`), merging `.gitignore` (D1); verify `git status` shows no deleted or modified template file and `.env`/`vendor/` are ignored
- [x] 1.3 Set `config.platform.php = 8.3.0` and `require.php = ^8.3` in `composer.json`, then run a full `composer update` (D2); verify `composer.lock` is created and `composer show --locked` lists the Laravel major recorded in 1.1 (or the fallback major from D2)
- [x] 1.4 In a `php:8.3-cli` container with the repo mounted (with `git` and `unzip` installed, and Composer copied from the `composer:2` image), run `composer check-platform-reqs --lock` (D2); verify it reports no unmet requirement
- [x] 1.5 Run the kit's post-create steps by hand: copy `.env.example` to `.env`, `php artisan key:generate`, create `database/database.sqlite` and `php artisan migrate`, keeping `APP_LOCALE=en` (D1); verify `php artisan about` lists locale `en` and `.env` is not tracked by git

## 2. Base application behavior (`app-foundation`)

- [x] 2.1 Ensure Pest is the test runner (keep the kit's Pest setup, or add Pest and convert with Drift; D3), that `phpunit.xml` uses in-memory SQLite, and add `$this->withoutVite()` to the base `TestCase::setUp()` (D4); verify `php artisan test` passes with no MySQL server running and no `public/build` folder
- [x] 2.2 Add a feature test "The root URL returns the Welcome page" (status 200, Inertia component `Welcome`) and one for the English locale (`app()->getLocale() === 'en'`); verify both pass
- [x] 2.3 Run `npm ci` and then `npm run build`; verify exit code 0 and that `public/build/manifest.json` exists (D6)

## 3. Errors without internal details (`error-pages`)

- [x] 3.1 Add `tests/Feature/ErrorPagesTest.php` with the shared leak assertion (no `base_path()`, `storage_path()`, "Stack trace", `#0 ` or the given secret) and the 404 scenario with debug off (D5); verify it passes
- [x] 3.2 Add the 500 scenario with a route registered inside the test that throws `RuntimeException('boom-secret-1234')` (D5); verify it passes and no test route exists in `routes/`
- [x] 3.3 Check that the local PHP has `pdo_mysql` enabled (`php -m`), then add the unreachable-database scenario: an `unreachable` connection (`mysql` driver, host `db.invalid`, password `db-secret-5678`, 1 s timeout) used only by the session (`session.driver=database`, `session.connection=unreachable`), with `database.default` left on SQLite, an `extension_loaded('pdo_mysql')` assertion and a request to `/login` (D5); verify it passes with no teardown error, doesn't leak `db-secret-5678` or `db.invalid`, and takes under 3 s

## 4. Documentation and final checks

- [x] 4.1 Update the `context:` block of `openspec/config.yaml` and the "Stack" section of `AGENTS.md` with the installed stack, the versions from 1.1/1.3, the PHP 8.3 platform, the `en` locale and the build/test commands, and add the D8 exemption to AGENTS.md "Code conventions" (D7, D8); verify both name Laravel, Inertia, Vue 3 + TypeScript, Tailwind, Pest, `en` and the PHP 8.3 platform, and that "Code conventions" states the exemption and that it ends with T-02
- [x] 4.2 Write `README.md` with requirements (PHP 8.3+ with `pdo_sqlite` and `pdo_mysql`, Composer, Node 22+), setup, build and test commands, the D8 exemption note, and pointers to `backlog/` and `openspec/` (D6, D7, D8); verify each documented command runs as written, in that order, on a fresh clone
- [x] 4.3 In a `php:8.3-cli` container prepared as in 1.4 plus `docker-php-ext-install pdo_mysql`, run `composer install` and `php artisan test` (D2, risk "Local PHP 8.5"); verify the suite exits 0 there, including the unreachable-database test
- [x] 4.4 Update the backlog trace in Spanish, matching each task's `language: es` (D2, D8, risk "The backlog still says PHP 8.4…"):
  - T-01 "Reglas propuestas" and T-04's PHP Pendiente: "T-01 fija PHP 8.3 en `config.platform.php`; pasar a 8.4 es subir esa fijación y MODIFICAR el requisito 'Minimum PHP platform'".
  - T-20: its PHP image follows 8.3.
  - T-02: "termina la excepción D8 de T-01 a la regla de repositorios: añade `UserRepository` para el código de auth que quede".
  - T-02 y T-03: el kit trae unos 20 errores de tipos (`tsconfig.json` y `AppHeader.vue`). Corregirlos y añadir la comprobación de tipos al tocar cada componente (D6).

  Verify that `grep -n "8.4" backlog/tasks/T-01-*.md backlog/tasks/T-04-*.md` shows no line presenting 8.4 as the chosen version, and that T-02 mentions `UserRepository` and D8
- [x] 4.5 Run `openspec validate project-foundation --strict`; verify it passes

## Workflow follow-up

- Run `/review-change project-foundation` before implementing.
- Commit each step with `/git-commit` (footer `Change: project-foundation`, `Task:` step, `Backlog: T-01`).
- When all steps are checked, `/ship` validates, archives the change into `openspec/specs/` and sets T-01 to `done`.
