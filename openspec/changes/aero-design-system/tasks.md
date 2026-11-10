# Tasks

> Backlog task: **T-03**. Requirements: `specs/aero-design`, `specs/app-foundation` (delta). Approach: `design.md`.

## 1. Tooling

- [x] 1.1 Install `pestphp/pest-plugin-browser` (Composer dev), `playwright` (npm dev) and Chromium. Add the `Browser` suite to `phpunit.xml`, plus `Tests\BrowserTestCase` (no `withoutVite()`) bound in `tests/Pest.php` (design D11). Add `tests/Browser/Screenshots` to `.gitignore`. Verify a smoke test that visits `/login` and sees "Log in" passes with `php artisan test --testsuite=Browser` after `npm run build`. Locally on Windows, `php artisan test` needs PHP's `sockets` extension, which is off in this machine's php.ini; run `php -d extension=sockets vendor/bin/pest` instead (CI has the extension).
- [x] 1.2 Spike the plugin APIs listed in design D11:
  - a sign-in that survives across requests;
  - `script()` and `getComputedStyle`;
  - viewport;
  - dark colour-scheme and reduced-motion emulation;
  - hover and mouse down/up;
  - request blocking or rerouting, and page-script swapping for T-06.

  Results (design D11, "Spike results"): sign-in works in one chain, no session-driver change needed, reduced motion and dark mode work, held-down press does not (manual check), request blocking does not (token fallback). Verified by `tests/Browser/SmokeTest.php`, which signs in and reaches `/dashboard`.
- [x] 1.3 Add `vue-tsc` and the `types` script, and fix the kit's type errors (design D13). Verify `npm run types` exits 0 (spec app-foundation, "Type check passes").
- [x] 1.4 Verify that `php artisan test --testsuite=Unit,Feature` passes with no `public/build` folder and no MySQL (spec app-foundation, "Test suite passes without a database server or built assets"), and that the full `php artisan test` passes after `npm run build` ("Browser tests pass with a build and no database server").
  - 2026-11-10, local Windows: `php artisan test --testsuite=Unit,Feature` passes without `public/build`; `--testsuite=Browser` passes (21 tests, 47 s). All three suites together pass with `vendor/bin/pest` (91 tests, 53 s). `php artisan test` with all three suites at once hangs after the Feature suite on this machine only; likely the Collision child process waiting on pipes held by the Playwright server. CI (Linux) runs `php artisan test` and is checked in 8.1.

## 2. Tokens, theme and light mode

- [x] 2.1 Create `resources/css/aero/tokens.css`, `themes/sky.css` and `base.css`, import them from `app.css`, and render `<html data-theme="sky">` in `app.blade.php` (design D1, D2, D4). Verify with a browser test that `/login` has `data-theme="sky"` ("Pages carry the default theme").
- [x] 2.2 Remove `useAppearance.ts`, the `initializeTheme()` call and the `.dark` block in `app.css` (design D5). Verify with browser tests "The system prefers dark" and "A stored starter-kit preference asks for dark" (localStorage `appearance=dark`) on `/login` and `/dashboard`. `/styleguide` is added to both in 3.4.
- [x] 2.3 Add Selawik woff2 under `resources/aero/fonts/` with `@font-face`, set the font stack in the tokens and in Tailwind's `fontFamily.sans`, and remove the `fonts.bunny.net` link (design D6). Verify `npm run build` exits 0 and the built CSS references the font file.
- [x] 2.4 Draw `resources/aero/backgrounds/sky.svg` (under 400 KB) and wire it into `--aero-background-image` with the gradient fallback token. Create `resources/aero/CREDITS.md` with a row for each asset (design D7). Verify with a Pest feature test that every file under `resources/aero/{backgrounds,icons,fonts}` has a row with an origin and a date ("Every asset is logged"), and that the test fails when a row is removed.

## 3. Components and styleguide

- [x] 3.1 Add the `/styleguide` route (`auth`, named `styleguide`), a `Styleguide.vue` page skeleton with the four section headings (`AeroBackground` is added in 3.2), and the sidebar "Styleguide" nav item (design D9). Verify feature tests "The owner opens the styleguide" (200, component `Styleguide`) and "A visitor without a session cannot see the styleguide".
- [x] 3.2 Build `AeroBackground` (layers, bubbles, slot) and `AeroGlassPanel` (blur, reflection, no-blur fallback) (design D3, D4). Verify browser tests:
  - "The panel is translucent and blurred";
  - "The fallback panel is almost opaque and readable", with the contrast helper from design D11;
  - "A theme token reaches the components".
- [x] 3.3 Build `AeroButton` (rectangle and orb, forced states, hover inside `@media (hover: hover)`, `:active`, disabled) and fill the "Buttons" section with both counters (design D3, D9). Verify browser tests:
  - "The four states side by side";
  - "Real hover and press change the look";
  - "Pressing and releasing outside does not stick";
  - "The enabled button responds to a click";
  - "The disabled button ignores clicks and Tab".

  If a scenario uses a fallback from 1.2, record it in design "Manual checks".
- [x] 3.4 Build `AeroInput` (label, error with `aria-invalid` and `aria-describedby`, autofill neutralised) and `AeroCheckbox` (native input, label area of at least 44x44 px), and fill the "Inputs" and "Checkbox" sections (design D3). Add `/styleguide` to the 2.2 dark-mode tests. Verify browser tests:
  - "Typing and an error message";
  - "Mouse and keyboard toggle the checkbox";
  - "The label toggles and the area is large enough";
  - the 2.2 dark-mode tests, now covering `/styleguide` as well.

## 4. Aero login

- [x] 4.1 Rewrite `AuthSimpleLayout.vue` and `Login.vue` with the Aero components and drop the manual `tabindex` values (design D10). Verify:
  - browser tests "The login looks Aero" (data-aero hooks, no `[data-slot]`) and "The Aero login still signs in";
  - a feature test that `resources/js/pages/auth/Login.vue` does not contain `@/components/ui`;
  - the existing `OwnerAuthTest` and `AuthenticationTest` feature tests still pass.
- [x] 4.2 Verify with browser tests "The credentials error is readable" and "A stray dark class does not change Aero" on the Aero login.

## 5. Accessibility and adaptation

- [x] 5.1 Verify with browser tests that walk every control on `/login` and `/styleguide` with Tab the scenario "Focus is visible on every control": every enabled control is reached, and the focused outline or box-shadow is at least 2 px and absent unfocused. Fix the components until it passes.
- [x] 5.2 Verify the scenario "No animation with reduced motion" on `/login` and `/styleguide` with a browser test, or with the fallback from 1.2 recorded in design "Manual checks".
- [x] 5.3 Verify the scenario "Pages fit a 375 px phone" at 375x667 on `/styleguide` and `/login`: `scrollWidth` at most 375, every control visible, every interactive control at least 44x44 px. Fix the layout until it passes.
- [x] 5.4 Verify the scenario "The background image is blocked" on `/login` with a browser test, using request blocking or the token fallback from design D11. The test checks the gradient, the contrast with `aero-no-blur`, and a successful sign-in.

## 6. Branding and owner-auth browser checks

- [x] 6.1 Show "AeroPlayer" in `AppLogo.vue`, remove the kit's external links from `AppSidebar.vue` and `AppHeader.vue`, and set `APP_NAME=AeroPlayer` in `.env.example` (design D15). Verify the browser test "No starter-kit links or name" on `/dashboard`.
- [x] 6.2 Add `tests/Browser/OwnerAuthBrowserTest.php` with the three checks T-02 made by hand (design D12). Verify that it passes.

## 7. Aero skill

- [x] 7.1 Write `.claude/skills/frutiger-aero/SKILL.md` and `references/{traits,tokens,components,accessibility}.md`, crediting the community skill (MIT) (design D8). Verify feature tests:
  - "The skill file exists with its frontmatter";
  - "The skill covers traits, components and accessibility", checking headings and component paths;
  - "The skill lists exactly the declared tokens", which also fails when a token is added to the CSS only, and when a `.vue` file under `resources/js/components/aero/` declares an `--aero-*` property (design D3, D8).

## 8. CI and docs

- [x] 8.1 Update `.github/workflows/tests.yml`: `npm run types` and `npx playwright install --with-deps chromium` before the build and tests (design D14). Verify the workflow run on the pushed commit is green, or, before pushing, that the same commands pass locally.
  - 2026-11-10: the same commands pass locally (types, Chromium install, build, all suites with `vendor/bin/pest`). The first run on GitHub, before this step, failed at Tests as expected (no Chromium). The green run is confirmed on the next push.
- [x] 8.2 Update `README.md` (Playwright install, `npm run types`, the suites, light mode only) and `openspec/config.yaml` (browser tests, type check, Aero components and skill). Verify that the README commands run as written on a clean checkout.
  - 2026-11-10: in a clean copy of the publishable files (`git ls-files -co --exclude-standard`), the README commands ran as written: composer install, `.env`, key, migrate, `npm ci`, Chromium, `npm run types`, `npm run build`, `--testsuite=Unit,Feature` (74 tests) and `--testsuite=Browser` (36 tests), all exit 0. `app:create-owner` was skipped because it is interactive by design.
- [x] 8.3 Integration check: verify `npm run types`, `npm run build` and the full `php artisan test` all pass together after groups 1–8.
  - 2026-11-10: `npm run types` 0, `npm run build` 0, all three suites with `vendor/bin/pest`: 110 tests, 440 assertions, 0 failures (81 s).

## 9. Manual checks and visual validation

- [x] 9.1 Open a new Claude Code session in the repo and check that the skill "frutiger-aero" is listed. Record the date and result in design "Manual checks".
  - 2026-11-10: Claude Code listed the skill "frutiger-aero" (with its description) in the session where it was written, as soon as the file existed. Recorded in design "Manual checks".
- [ ] 9.2 With the app built and served on `localhost`, take screenshots of `/login` and `/styleguide` at 1280 px and 375 px, store them in `openspec/changes/aero-design-system/validation/`, and have the owner review them against the four traits. Record in design "Validación visual" the date, the screenshots, one verdict per trait (all "aprobado") and each requested correction as [x] once done. Verify `npm run types`, `npm run build` and the full test suite still pass after the corrections.

## Workflow follow-up

- `/review-change aero-design-system` before `/opsx-apply`.
- At `/ship`: apply the backlog follow-ups from design (T-20, T-06, T-23 and T-21), set T-03 `status: done`, and archive the change.
