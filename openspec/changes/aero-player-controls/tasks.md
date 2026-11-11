# Tasks

> Backlog task: **T-21**. Requirements: `specs/aero-design` (delta). Approach: `design.md`.
>
> Local test runs on Windows: `php vendor/bin/pest` with `PHPRC` and `PHP_INI_SCAN_DIR` set (sockets), after `npm run build`. Use explicit selectors (`#id`, `[data-aero=…]`) in browser tests.

## 1. Icons and sample assets

- [x] 1.1 Draw the six icons from design D4 (`previous`, `play`, `pause`, `next`, `volume`, `note`) as 24x24 SVG files with `fill="currentColor"` in `resources/aero/icons/`. Draw `resources/aero/samples/cover.svg` (design D7). Add one `CREDITS.md` row for each file. Add `samples` to the folders in `AeroAssetsTest`. Verify that the test passes, and that it fails when one row is removed ("Every asset is logged").
- [x] 1.2 Build `AeroIcon.vue` (prop `name`, raw SVG imports through `import.meta.glob`, `aria-hidden`, `data-aero="icon"`, `data-icon="<name>"`) (design D4). Verify that `npm run types` and `npm run build` exit 0. The icon is first used in group 3; 3.1 checks the "note" icon in the cover fallback, and 4.4 checks the play/pause icons.

## 2. Slider

- [x] 2.1 Build `AeroSlider.vue` (design D1):
  - native range input with `v-model`, `min`, `max`, `step`, `disabled`, `label` and `valueText`;
  - the track, fill and thumb for Webkit and Firefox;
  - an input of 44 px height;
  - no transitions on the thumb.

  Add any new tokens to `tokens.css` or `themes/sky.css` (design D6) and to the skill's `references/tokens.md`. Verify that `AeroSkillTest` passes.
- [x] 2.2 Add the "Sliders" section to `Styleguide.vue` with "Volume", "Seek" and a disabled slider, each with its value label to the right of the track (design D8). Create `tests/Browser/StyleguideSectionsTest.php`, which reads the headings of "Buttons", "Glass panel", "Inputs", "Checkbox" and "Sliders" by id ("The owner opens the styleguide", design D9). Add `tests/Browser/AeroSliderTest.php` with:
  - "The keyboard moves the slider one step";
  - "The pointer moves the slider and stops at the end", using `drag()` to the value label;
  - "The slider stays within its limits", including End and Home;
  - "A disabled slider ignores input". If the drag waits for an enabled element, use the fallback from design D9 and record it in design "Manual checks".

  Add `AeroSlider` to the skill's components table and to `references/components.md`. Verify that these tests and `AeroSkillTest` pass.

## 3. Cover

- [x] 3.1 Build `AeroCover.vue` with the three states, the `onMounted` completeness check and the fallback that uses the "note" icon and the sky tokens (design D2). Add the "Cover" section with the "With image", "No image" and "Broken image" samples at 96 px (design D7, D8). Add "Cover" to `StyleguideSectionsTest`. Add `tests/Browser/AeroCoverTest.php` with:
  - "A song without a cover", which also checks that the fallback contains `[data-aero=icon][data-icon=note]`;
  - "The cover image fails to load";
  - "A cover with an image";
  - "Every cover keeps its size".

  Add `AeroCover` and `AeroIcon` to the skill. Verify that these tests and `AeroSkillTest` pass.

## 4. Player bar

- [x] 4.1 Add the `size` prop to `AeroButton` with the large orb token (design D5). Add `resources/js/lib/formatTime.ts` (`m:ss`, and `h:mm:ss` from one hour) (design D3). Verify that the existing `AeroButtonTest` still passes, and that `npm run types` exits 0.
- [x] 4.2 Build `AeroPlayerBar.vue` (design D3):
  - orbs for "Previous" and "Next", and the large orb for "Play"/"Pause";
  - the props, events and per-control disabled props;
  - the times and the seek `aria-valuetext`;
  - single-line title and artist with ellipsis, `title` and `min-height`;
  - the `notice` slot inside a status region that is always rendered;
  - the `notice-actions` slot outside that region, with 44 px links through `:slotted(a)`;
  - the `extra` slot;
  - ids from `useId()`;
  - the one-row layout and, below 640 px, the wrapped layout.

  Verify that `npm run types` and `npm run build` exit 0.
- [x] 4.3 Add the "Player bar" section to `Styleguide.vue` with both sample bars (design D8):
  - bar 1 with the sample cover and the "Toggle text", "Long title" and "Long song" buttons ("Long song" sets the position to 0 and the duration to 3725);
  - bar 2 with no cover, the disabled controls, the notice and its actions, and the "Extra" toggle.

  Add "Player bar" to `StyleguideSectionsTest`. Verify that this test passes with all seven headings.
- [x] 4.4 Add `tests/Browser/AeroPlayerBarTest.php` with:
  - "The bar has the basic controls";
  - "Play and pause change the control without sound", checking `data-icon="pause"` after the first press and `data-icon="play"` after the second;
  - "The bar shows the times";
  - "Seeking updates the elapsed time";
  - "A song of an hour or more";
  - "Empty title and artist do not move the bar";
  - "A long title on a phone";
  - "Disabled controls cannot be used";
  - "A notice with a button and a link";
  - "An extra toggle button in the bar".

  "Disabled controls cannot be used" drags the "Seek" thumb with `drag()` (design D9). Verify that the tests pass. Fix the bar until they do.
- [x] 4.5 Update the skill (design D10):
  - `AeroPlayerBar` in the components table and in `references/components.md`, with its props, events and slots;
  - the "Classic media players" section names AeroPlayerBar and AeroSlider and links to `references/components.md`;
  - `references/traits.md` points to the bar.

  Extend `AeroSkillTest` with the three new component paths and that link ("The skill covers traits, components and accessibility"). Verify that it passes.

## 5. Accessibility across the new sections

- [x] 5.1 Add the reduced-motion test "No animation on the player controls with reduced motion" to `AeroAccessibilityTest`: press "Play" and move both sliders with the keyboard, then read `animation-name` and the transition durations in the three sections. Add `tests/Feature/AeroSliderSourceTest.php`, which checks that the range pseudo-element rules of `AeroSlider.vue` declare no `transition` or `animation` (design D9). Verify that both tests pass.
- [x] 5.2 Run the existing tests on `/styleguide`: the focus walk ("Focus is visible on every control"), the 44x44 px touch areas and the 375 px layout ("Pages fit a 375 px phone"). The new controls are now part of the page. Verify that they pass. Fix the components until they do. The slider's input height and the bar's wrapped layout are the likely spots.

## 6. Integration

- [x] 6.1 Verify that `npm run types`, `npm run build` and all three test suites pass together after groups 1–5. Locally use `vendor/bin/pest`; CI runs `php artisan test` on the next push.

## 7. Visual validation

- [x] 7.1 With the app built and served on `localhost`, take screenshots of the "Sliders", "Cover" and "Player bar" sections of `/styleguide` at 1280 px and 375 px. Store them in `openspec/changes/aero-player-controls/validation/`. Have the owner review them against the four traits, including the hand-made icons and the sample cover (design D4, D7). Also check the slider by hand on the owner's phone browser. Record in design "Validación visual":
  - the date;
  - the screenshots;
  - one verdict per trait, all "aprobado";
  - each requested correction, marked [x] once done.

  Verify that `npm run types`, `npm run build` and the full test suite still pass after the corrections.

## Workflow follow-up

- `/review-change aero-player-controls` before `/opsx-apply`.
- At `/ship`:
  - apply the backlog follow-ups from design (T-06, T-23, T-13, T-16, T-17, T-07, T-08);
  - set T-21 to `status: done`;
  - archive the change.
