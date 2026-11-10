# Design

## Context

See proposal.md (Why). Current state that shapes the approach:

- **Frontend:** the starter kit (v1.0.2) has Tailwind **3.4** with `darkMode: ['class']` and shadcn-vue components under `resources/js/components/ui`. Its CSS variables are in `resources/css/app.css`, with a `.dark` block.
  - At this kit version, the shadcn-vue components do **not** emit `data-slot`, so the "no `data-slot`" check on the login only guards against a future kit update. The real guard is the import check on `Login.vue`.
- **Theme code:**
  - `resources/js/composables/useAppearance.ts` reads the `appearance` key from **localStorage**, not from a cookie as T-03 assumed, and also follows `prefers-color-scheme`.
  - `app.ts` calls `initializeTheme()` on every page load.
  - `AppearanceTabs.vue` and the appearance settings page are already gone (single-owner-login).
- **Login:** `Login.vue` uses the kit's `Button`, `Checkbox`, `Input` and `Label`, plus odd `tabindex` values (a `div` with `tabindex="3"`, two controls with `tabindex="4"`), inside `AuthLayout` → `AuthSimpleLayout`. Login is the only page left that uses that layout.
- **Fonts:** `app.blade.php` loads Instrument Sans from `fonts.bunny.net`.
- **Tests and CI:**
  - `tests/TestCase.php` calls `withoutVite()` for every test (design D4 of project-foundation), and `phpunit.xml` sets `SESSION_DRIVER=array` and in-memory SQLite.
  - CI (`tests.yml`) already runs `npm ci` and `npm run build` before `php artisan test`.
- **Type errors:** the kit has about 20 `vue-tsc` errors (project-foundation design): two unresolved `types` entries in `tsconfig.json`, and props typed `unknown` such as `auth` in `AppHeader.vue`.
- **Kit branding:** `AppSidebar.vue` (footer) and `AppHeader.vue` (right nav) link to the kit's GitHub repo and docs, and `AppLogo.vue` shows "Laravel Starter Kit".

## Goals / Non-Goals

**Goals**

- One place for the Aero look (tokens plus component CSS) that T-18 can theme without touching components.
- Browser tests that later UI tasks reuse as they are. T-06 and T-23 need to run JavaScript in the page and replace the Spotify SDK script.
- Automated checks for everything the spec states, except the owner's visual approval and the skill listing.

**Non-Goals**

- Aero versions of the kit screens (dashboard, sidebar, header). They only lose dark mode and the kit branding here; T-25 migrates them.
- Final asset choice beyond a first proposal: the owner may swap the background or font during the visual validation (Open Questions).

## Decisions

### D1. Tailwind for layout, component CSS for the look

Tailwind stays for flex, grid, spacing and breakpoints. Everything that makes something look Aero (gradients, gloss, glass, relief, focus ring) lives in each component's `<style scoped>` and only reads `--aero-*` tokens. Global Aero CSS lives in `resources/css/aero/`, imported by `app.css`:

- `tokens.css`: fixed tokens.
- `themes/sky.css`: themable tokens.
- `base.css`: the reduced-motion rule, the no-blur fallback and the focus-ring helper.

*Alternatives rejected:*
- Restyling shadcn-vue. Its markup and variants fight the gel look, and T-25 removes it anyway.
- Expressing the look as Tailwind theme extensions or arbitrary values. Multi-layer gradients in utility classes are unreadable, and T-18 needs runtime theming through CSS variables.

### D2. Tokens split into fixed and themable

- `tokens.css` declares fixed tokens on `:root`: radii, font family and sizes, blur radius, focus-ring width and the minimum touch size.
- `themes/sky.css` declares the themable tokens under `:where(:root), [data-theme="sky"]`: sky colours, text colours, the error colour, the panel background and its fallback, the panel border and highlight, the button gel stops, shadows, focus-ring colour, `--aero-background-image` and `--aero-background-fallback`.
- Sky tokens get descriptive names (`--aero-sky-top`, `--aero-sky-horizon`) because T-16 reuses them.
- `app.blade.php` renders `<html data-theme="sky">`.
- Components never use literal colours that a theme should change.

The final token list is settled during implementation. The skill test (D8) keeps the skill in sync with it, whatever it ends up being.

*Alternative rejected:* all tokens on `:root` with T-18 moving them later. That is the same work, done twice.

### D3. Components

Files in `resources/js/components/aero/`. Each root element carries `data-aero="<name>"`: `button`, `glass-panel`, `input`, `checkbox`, `background`. Components only **read** `--aero-*` tokens and never declare them; a component that needs a local variable uses another prefix (for example `--_btn-*`), so all tokens live in `resources/css/aero/` (D2, D8).

- **`AeroButton`:**
  - Props: `shape: 'rect' | 'orb'`, `type`, `disabled`, and `state?: 'hover' | 'pressed'`.
  - The `state` prop forces a look for the static samples on the styleguide. The forced `[data-state=hover]` and `[data-state=pressed]` samples share their rules with the real states.
  - The real `:hover` sits inside `@media (hover: hover)`, so a tap leaves no sticky hover. The forced `[data-state=hover]` sample stays outside that media query, so the styleguide shows it on a phone too.
  - Pressed is `:active` only. The browser drops `:active` when the button is released outside, which covers the spec's "does not stick" scenario.
  - Disabled uses the native `disabled` attribute, which removes the button from the Tab order and ignores clicks.
  - The gel is drawn with two backgrounds: a highlight gradient on the top half over a base gradient, plus an inset shadow.
- **`AeroGlassPanel`:**
  - Translucent `--aero-panel-bg` with `backdrop-filter` and `-webkit-backdrop-filter` at `blur(var(--aero-blur))`.
  - `--aero-blur` is small (about 6px), because a large blur reads as modern glassmorphism rather than Vista or 7.
  - A top reflection is drawn with a pseudo-element.
- **`AeroInput`:**
  - Props: `id`, `label`, `type`, `modelValue`, `error`, `autocomplete` and native attributes.
  - Renders the label, the input and the error message. When `error` is set, the input gets `aria-invalid="true"` and `aria-describedby` pointing at the message id.
  - Browser autofill is neutralised with an inset box-shadow on `:-webkit-autofill`, so the yellow background never shows.
- **`AeroCheckbox`:**
  - A real `<input type="checkbox">` with `appearance: none`, drawn as an Aero box with a CSS tick, inside a `<label>`.
  - The label has `min-height` and padding so the clickable area reaches 44x44 px.
  - Bound with `v-model` (a boolean), so form posts stay as they are.
- **`AeroBackground`:**
  - A fixed full-viewport layer with `background-image: var(--aero-background-image), var(--aero-background-fallback)`. The gradient is the last layer, so it paints first and stays visible if the image fails or is slow.
  - Slow floating bubbles are drawn with CSS animation. They are decorative and `aria-hidden`.
  - It renders a slot for the page content.

### D4. Reduced motion and the no-blur fallback are global rules

- **Reduced motion.** `base.css` has one `@media (prefers-reduced-motion: reduce)` rule on `*, *::before, *::after` that sets `animation: none !important` and `transition-duration: 0.01s !important`. It also covers the kit's `animate-spin` loader.
- **No-blur fallback.** The fallback is written once for both triggers:
  - `@supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px)))`;
  - the `.aero-no-blur` class on `<html>`.

  In both cases `AeroGlassPanel` switches to `--aero-panel-bg-fallback` (alpha ≥ 0.85) and drops the blur.

### D5. Light mode only

- Delete `useAppearance.ts` and the `initializeTheme()` call in `app.ts`, so nothing ever adds `dark`.
- Delete the `.dark` variable block from `app.css`, so a stray class does not darken the kit screens either.
- Aero CSS never reads `.dark`.
- Tailwind's `dark:` utilities in kit components stay for now, inert without the class (T-25 removes them).

*Alternative rejected:* keep the code and force `'light'`. It leaves dead code and a listener on `prefers-color-scheme`.

### D6. Font

- The font stack is `"Segoe UI", "Selawik", "Frutiger", "Myriad Pro", system-ui, sans-serif`.
- Selawik is Microsoft's open-source (OFL) fallback for Segoe UI with matching metrics. It is self-hosted as woff2 in `resources/aero/fonts/`, so Android and iOS look close to Windows.
- The `fonts.bunny.net` link in `app.blade.php` is removed, which also drops a third-party request.
- Tailwind's `fontFamily.sans` points to the same stack, so kit screens match.
- A font that fails to load falls back down the stack (`font-display: swap`).

*Alternatives rejected:* Frutiger, which is commercial. A Google-hosted look-alike, which means an external request and is less close than Selawik.

### D7. Background asset and provenance

- **First background:** a hand-made SVG, `resources/aero/backgrounds/sky.svg`. It has a sky gradient, soft clouds, a green hill and a water line, and stays well under the 400 KB guide.
- **Loading:** Vite processes it through `url()` in `themes/sky.css`, so it gets a hashed URL.
- **Swapping:** the owner can swap it for a photo during the visual validation; that changes one token and one credits row.
- **Icons:** T-03 adds no icon files. The checkbox tick is CSS, and the login keeps the kit's `LoaderCircle` from lucide, a dependency rather than an asset. `resources/aero/icons/` is created when T-21 needs it.
- **Provenance log:** `resources/aero/CREDITS.md` holds one table row per file (`File | Origin | Added`).
- **Provenance test:** a Pest feature test lists every file under `resources/aero/{backgrounds,icons,fonts}` that exists and checks that each has a row with an origin and an ISO date.

### D8. The skill

- **Layout:**
  - `.claude/skills/frutiger-aero/SKILL.md` is a map of about 100 lines. Its `description` says to use it when creating or changing the app's interface.
  - The detail goes in `references/`:
    - `traits.md`: the four traits, with the classic players WMP 11, iTunes 7–9, Zune and Winamp as references;
    - `tokens.md`: every token with its role;
    - `components.md`: props, states and `data-aero` hooks, with file paths;
    - `accessibility.md`: focus, contrast 4.5:1, reduced motion, no-blur fallback, 44 px touch areas, light only.
- **Starting point:** the community skill `frutiger-aero` from `sickn33/agentic-awesome-skills` (MIT), credited in SKILL.md. Its parts for other frameworks are not copied.
- **Tests:** two Pest feature tests read files only:
  - one checks the frontmatter and that the trait sections and component paths exist;
  - one collects `--aero-[a-z0-9-]+` declarations (`name:`) from every CSS file under `resources/css/aero/` and compares that set with every `--aero-*` name mentioned anywhere in the skill folder. The two sets must be equal. The same test fails if any `.vue` file under `resources/js/components/aero/` declares an `--aero-*` property (D3).

### D9. Styleguide page

- **Route:** `GET /styleguide`, named `styleguide`, behind `auth`. It is a closure that renders `Styleguide`, like `dashboard`.
- **Layout:** the page uses `AeroBackground` directly, not the kit's `AppLayout`, which is shadcn-vue.
- **Content:**
  - each section is an `AeroGlassPanel` with its heading;
  - the "Buttons" section shows each shape in the four states with forced `state` props and `disabled`, plus one interactive button with a "Clicks: N" counter and a disabled one with "Disabled clicks: N";
  - the "Inputs" section holds the "Email" sample and an input with the sample error;
  - the "Checkbox" section holds one unchecked and one checked checkbox.
- **Navigation:** the kit sidebar gets a "Styleguide" nav item so the owner can reach the page.
- **Production:** the page stays in production. Only the owner can open it, and it is the reference for T-18.

### D10. Aero login

- **Layout:** `AuthSimpleLayout.vue` is rewritten as `AeroBackground` plus a centred `AeroGlassPanel` with the logo, the title and the slot. Only `Login.vue` uses it.
- **Login page:** `Login.vue` uses `AeroInput` for "Email" and "Password" (with `form.errors.*` as `error`), `AeroCheckbox` for "Remember me" and `AeroButton type="submit"` for "Log in".
- **Tab order:** the kit's manual `tabindex` values are removed, so the order follows the DOM. The `div` with `tabindex="3"` was a focus stop with no control.
- **Kept as is:** the `status` message and the `canResetPassword` prop.
- **Behaviour:** no change (owner-auth). Field names, `remember` and the post target stay the same.

### D11. Browser testing

- **Install:**
  - Composer dev dependency `pestphp/pest-plugin-browser`;
  - npm dev dependency `playwright`;
  - `npx playwright install chromium`.
- **Suite and base class:** tests live in `tests/Browser`, a third suite in `phpunit.xml`. `tests/Pest.php` binds them to a new `Tests\BrowserTestCase` with `RefreshDatabase`. That class sets the lowercase `pages` path like `TestCase` but does **not** call `withoutVite()`, because the browser needs the real build.
- **Running:**
  - `php artisan test` runs all three suites;
  - `php artisan test --testsuite=Unit,Feature` keeps the self-contained run (spec app-foundation).
- **Spike results (task 1.2, 2026-11-10, Windows, local Pest browser 4 + Playwright, same process as the test):**
  - **Sign-in and session:** works. `visit()` starts a new browser context on each call, so a sign-in and the next page must share one chain (`visit('/login')->fill(...)->press('Log in')->navigate('/dashboard')`). The server runs in the test's own PHP process, so `RefreshDatabase` with in-memory SQLite and `SESSION_DRIVER=array` are shared with the browser. No `file` session driver is needed.
  - **No `actingAs` in the plugin:** every browser test that needs the owner signs in through the form in its chain (about 2 s).
  - **`script()`:** works, but it evaluates an expression, not a `return` statement, and returns the value, so it cannot be chained.
  - **Viewport:** `resize(375, 667)` works.
  - **Dark colour scheme:** `inDarkMode()` works.
  - **Reduced motion:** works through the `visit()` options (`['reducedMotion' => 'reduce']`), which Pest forwards to the Playwright context. No fallback needed.
  - **Hover:** `hover()` works, and `matches(':hover')` confirms it.
  - **Pressed (held down):** no API for mouse down without release. The "Pressed" look is checked by hand (manual check).
  - **"Does not stick":** `drag(from, to)` presses on one element and releases on another. After the release, `matches(':active')` must be false. Testable.
  - **Selectors:** the plugin only treats a selector as CSS when it is explicit (`#id`, `.class`, `[attr]`). A bare tag such as `h1` or `body` is looked up as an id, a name or text, and the lookup waits until the timeout, which the run reports as "Target crashed". Use `#id` for tags.
  - **Request blocking:** no API. Fallback: `script()` sets `--aero-background-image` to a missing URL, which gives the same "image failed" state.
  - **Page scripts (T-06 and T-23):** `visit()` cannot inject an init script (only `Context::addInitScript()`, which is not reachable from `visit()`). Fallback: the Spotify SDK URL is read from config, and the test points it at a test-only route that serves a fake SDK.
  - **Browser sessions across 1.2:** `localStorage` changes work, and `appearance=dark` already turns `<html>` dark today (the scenario that task 2.2 must turn red-then-green).
- **Fallbacks, decided with the spike:**
  - **Request blocking missing:** token override with `script()`, described above.
  - **Mouse held down missing:** "Pressed" becomes a manual check, recorded in "Manual checks". "Does not stick" uses `drag()`.
- **Test-only fake SDK route (T-06):** deferred to T-06, which owns the Spotify SDK.
- **Contrast:** a small JavaScript helper computes the WCAG ratio from computed colours. It only runs with `aero-no-blur`, because a translucent panel over an image has no single background colour (the same reason axe reports it as incomplete). Contrast on real glass is part of the owner's visual validation.

### D12. Automate T-02's manual checks

`tests/Browser/OwnerAuthBrowserTest.php` covers the three scenarios that single-owner-login checked by hand:

- the login page has no sign-up or reset link;
- the user menu holds only "Log out" and no link returns 404;
- going back after logout shows no protected page.

The Pest server runs on `127.0.0.1`, a secure context, so history encryption works. These tests cover existing `owner-auth` requirements and change no spec.

### D13. Type check

- Add `vue-tsc` as an npm dev dependency, in a major version that supports the installed TypeScript; if npm reports a peer conflict, pin TypeScript to the range `vue-tsc` declares.
- Add the script `"types": "vue-tsc --noEmit"`.
- Fix the kit errors, typing props such as `auth` with the existing `SharedData` and `User` types in `resources/js/types`, and remove the unresolved `types` entries from `tsconfig.json`.
- CI runs `npm run types` before the build.

### D14. CI

`tests.yml`:

- after `npm ci`, runs `npm run types` and `npx playwright install --with-deps chromium`;
- keeps `npm run build` and `php artisan test`, which now include the browser suite.

The lint workflow is unchanged.

### D15. Kit branding

- `AppLogo.vue` shows "AeroPlayer".
- The external `footerNavItems` in `AppSidebar.vue` and `rightNavItems` in `AppHeader.vue` are removed.
- `.env.example` sets `APP_NAME=AeroPlayer`.

## Risks / Trade-offs

- **[Browser tests are slow and heavy]** Chromium download and longer CI runs. → Only Chromium is installed. The unit and feature suites still run alone with `--testsuite=Unit,Feature`.
- **[Plugin gaps on Windows or in emulation]** → Spike first (D11), with the fallbacks decided above.
- **[Contrast of the real glass cannot be automated]** → Automated on the fallback; the owner judges the glass during the visual validation.
- **[Tailwind preflight or kit styles override component CSS]** → Scoped styles on component roots and explicit properties. The styleguide tests catch regressions.
- **[More type errors than the ~20 recorded]** → Fix what the kit and this change touch. If an error needs a larger refactor, stop and raise it, per AGENTS.md, rather than silence it with `any` or `@ts-ignore`.
- **[Mixed styles until T-25]** The dashboard and sidebar stay shadcn-vue in light mode. → Accepted (decision in T-03).
- **[Docker test service lacks Chromium]** → Follow-up for T-20.

## Migration Plan

- No data migration.
- Developers run `composer install`, `npm ci`, `npx playwright install chromium` and `npm run build` before `php artisan test`. The README documents this.
- Rollback is a git revert. Nothing is stored server-side.

## Backlog follow-ups (applied at /ship)

- **T-20:** the `test` service must install Playwright and Chromium and build the frontend before the browser suite.
- **T-06 and T-23:** the spike's finding on script swapping (D11).
- **T-21:** extend the skill and the styleguide with its components, and create `resources/aero/icons/` with credits.

## Open Questions

- Final background and font: the owner may keep the hand-made SVG and Selawik or pick others during the visual validation. A swap changes tokens, files and credit rows only.

## Manual checks

<!-- Filled during /opsx-apply: skill listed in a new Claude Code session; any browser fallback from D11. -->

## Validación visual

<!-- Filled during /opsx-apply: date, screenshots of /login and /styleguide at 1280 px and 375 px (stored in this change's `validation/` folder so they are archived with it), a verdict per trait (glossy y cristal, naturaleza y cielo, reproductores clásicos, skeuomorfismo) and the owner's corrections as [x] items. -->
