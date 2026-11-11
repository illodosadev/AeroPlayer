# Proposal

Implements backlog task T-03 (sistema de diseño Frutiger Aero — base y login).

## Why

A convincing 2000s look is the project's main success measure, and every later screen needs the same visual base. Without shared tokens, components and rules, each task would reinvent glossy buttons and glass panels and the look would drift. The login, the first screen the owner sees, still uses the kit's flat shadcn-vue style.

## What Changes

- `--aero-*` design tokens as CSS custom properties: fixed tokens in one file, the default "sky" theme's colours, gradients, shadows and background in a theme file that T-18 can override with `data-theme`.
- Vue components with their own CSS: `AeroButton` (rectangle and orb), `AeroGlassPanel`, `AeroInput`, `AeroCheckbox`, `AeroBackground`, with visible focus, reduced motion and a no-blur fallback.
- A signed-in `/styleguide` page showing every component and state.
- The login is restyled with these components, without shadcn-vue.
- **BREAKING (UI):** light mode only; the kit's theme code is removed, so `<html>` never gets `dark`.
- A Claude skill, `.claude/skills/frutiger-aero`, with the style rules, component map and token list, kept in sync with the CSS by a test.
- A provenance log for visual assets.
- Pest 4 browser tests (Playwright + Chromium) in `php artisan test`, also automating T-02's three manual browser checks.
- Frontend type check with `vue-tsc`, fixing the kit's type errors.
- The kit's external "Github Repo" and "Documentation" links are removed; "Laravel Starter Kit" becomes "AeroPlayer".

## Capabilities

### New Capabilities

- `aero-design`: the Aero visual system: tokens and skill, base components and states, styleguide, Aero login, light mode only, accessibility, mobile layout and asset provenance.

### Modified Capabilities

- `app-foundation`: "Self-contained automated test suite" covers the unit and feature suites only (browser tests need a build and Chromium); new requirement "Frontend type check".

## Non-goals

- `AeroSlider`, the player bar and the cover fallback (T-21).
- Themes and skins (T-18), the visualizer (T-16) and the Aero error pages (T-26).
- Migrating the kit's other screens and removing shadcn-vue (T-25).
- Login and logout behaviour (`owner-auth`, unchanged).
- Playwright in the Docker `test` service (T-20).

## Impact

- Frontend: new `resources/css/aero/` and `resources/js/components/aero/`, a `Styleguide` page; changes to `Login.vue`, the auth layout, `app.ts`, `app.css`, `app.blade.php`, sidebar and header.
- Assets: `resources/aero/` with `CREDITS.md`.
- Backend: a `/styleguide` route behind `auth`.
- Dev dependencies: `pestphp/pest-plugin-browser`, `playwright`, `vue-tsc`.
- Tests: new `tests/Browser` suite; `withoutVite()` stays for feature tests only.
- CI: installs Chromium, runs the type check.
- Docs: `README.md`, `openspec/config.yaml`.
