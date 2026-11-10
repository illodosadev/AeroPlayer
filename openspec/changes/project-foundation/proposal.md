# Proposal

> Backlog task: **T-01** — Base del proyecto Laravel + Inertia + Vue (`backlog/tasks/T-01-base-del-proyecto.md`).

## Why

AeroPlayer has no application code yet: the repository only holds the spec-driven template and the backlog. Every other task (login T-02, design system T-03, deployment T-04, Docker stack T-20, and all the player features) needs a known, tested, secure-by-default Laravel + Inertia + Vue base to build on, instead of each one scaffolding its own.

## What Changes

- Install the official Laravel Vue starter kit (Laravel, Inertia, Vue 3 with TypeScript, Tailwind, shadcn-vue, Fortify auth) at the repository root, next to the template folders, with Pest as the test runner.
- Pin the PHP platform to **8.3** in `composer.json`, so dependencies resolve for the lowest PHP version the Hostinger subdomain may run (T-04 Pendiente).
- Keep the locale `en`.
- Keep the starter kit's auth as it is (registration, email verification, password reset, settings, 2FA). Trimming it is T-02.
- Add Pest tests proving error responses never leak internal details: unknown route (404), uncaught exception (500) and unreachable database (500), all with debug off.
- Document the installed stack in `openspec/config.yaml` (`context`) and add a README section on installing dependencies, building the frontend and running tests.

## Capabilities

### New Capabilities

- `app-foundation`: the base application contract — the root page is an Inertia-rendered Vue page, the UI locale is English, the frontend builds, and the Pest suite runs without an external database.
- `error-pages`: how the app responds to errors in production mode — correct status codes with no stack traces, exception messages, file paths, hosts or credentials. T-26 later extends it with the Aero look.

### Modified Capabilities

_None — `openspec/specs/` is empty._

## Non-goals

- Docker stack (T-20), removing registration and other auth features (T-02), the Aero design system (T-03), deployment and CI workflows (T-04), Aero error pages (T-26).
- Restyling or removing any starter kit screen.
- Any music-player feature.

## Impact

- **New code:** the whole Laravel application (`app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `storage/`, `tests/`, `composer.json`, `package.json`, Vite/TypeScript config).
- **Dependencies:** PHP 8.3+, Composer, Node 22+; in-memory SQLite for tests.
- **Repo files:** `.gitignore` merged; `openspec/config.yaml` and AGENTS.md updated; README added.
- **Repository rule (AGENTS.md):** the starter kit's own auth and settings code queries models directly. It stays unchanged under a temporary, explicit exemption until T-02, which reworks that code and adds `UserRepository`. New code in this change touches no models.
- **Backlog:** T-01's PHP rule and T-04's PHP Pendiente are updated to "8.3 pin", and T-20's image follows 8.3. Moving to 8.4 later means raising the pin and MODIFYING the "Minimum PHP platform" requirement.
- **Downstream:** T-02 MODIFIES "Root page renders the Welcome page component" (D11: "/" becomes a redirect), starts from this auth and ends the repository-rule exemption. T-03 MODIFIES "Self-contained automated test suite" (browser tests need a build). T-04's PHP decision stays open (the 8.3 pin keeps both options). T-20 builds its images from this app.
