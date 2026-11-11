# Design

## Context

See proposal.md (Why). Current state from aero-design-system (T-03):

- **Components:** `resources/js/components/aero/` holds `AeroButton` (rect and orb, fixed 44 px orb), `AeroGlassPanel`, `AeroInput`, `AeroCheckbox` and `AeroBackground`.
  - Each component reads `--aero-*` tokens and never declares them.
  - Local variables use another prefix (`--_base`).
  - `tests/Feature/AeroSkillTest.php` enforces both rules.
- **Global CSS:** `resources/css/aero/base.css` has the global `:focus-visible` outline and the reduced-motion rule. The reduced-motion rule covers `*`, `*::before` and `*::after`, but not the range pseudo-elements (`::-webkit-slider-thumb`, `::-moz-range-thumb`).
- **Assets:** `resources/aero/` has `backgrounds/` and `fonts/`; there is no `icons/` folder yet. `AeroAssetsTest` checks `backgrounds`, `icons` and `fonts` against `CREDITS.md`.
- **Browser tests:** Pest 4 browser plugin with Chromium.
  - Sign-in and the next page must run in one chain (`signedInOwnerVisit()`).
  - `script()` evaluates an expression.
  - Selectors must be explicit.
  - The plugin cannot block requests, so a broken image must be a real 404.
  - It cannot hold the mouse button down; `drag()` works.
- **Existing accessibility tests:** the focus walk, 44x44 px touch areas and 375 px layout tests on `/styleguide` cover every control on the page. New controls join them automatically.
- **Stack:** Vue 3.5 (`useId()` available); no SSR.

## Goals / Non-Goals

**Goals**

- A bar that T-06 can wire up only through props and events, with no visual work left.
- Hooks that later tasks fill without touching the bar:
  - the notice slot (T-23);
  - the extra-controls slot (T-13, T-16, T-17);
  - per-control disabled props (T-06, T-23).

**Non-Goals**

- A store or composable for player state. The styleguide keeps sample state in local refs; T-06 owns the real composable.

## Decisions

### D1. `AeroSlider` wraps a native range input

`<input type="range">` with `v-model` (number), `min`, `max`, `step`, `disabled`, `label` (rendered as `aria-label`) and an optional `valueText` (`aria-valuetext`). The root carries `data-aero="slider"`.

- **Behaviour from the browser:** keyboard (arrows, Home, End, Page keys), the `slider` role, the accessible value, clamping to the limits, and pointer capture while dragging outside the track. So the spec's keyboard, pointer, limits and disabled scenarios need no script.
- **Track:**
  - `appearance: none` on the input.
  - The track is drawn with `::-webkit-slider-runnable-track` and `::-moz-range-track`.
  - The track is an inset groove with an inner shadow: the WMP 11 relief.
  - The filled part uses a local `--_fill` percentage, set as an inline style from the value, in a two-stop gradient. Firefox uses `::-moz-range-progress` instead.
- **Thumb:** a small glossy gel ball drawn with `::-webkit-slider-thumb` and `::-moz-range-thumb`.
- **Touch area:** the input itself is `--aero-touch-min` tall and fills its width, so the touch area is at least 44x44 px while the track and thumb are drawn smaller.
- **Focus:** the global `:focus-visible` outline on the input.
- **Reduced motion:** no transitions on the thumb pseudo-elements, since the global rule does not reach them.

*Alternatives rejected:*

- A custom `div` with `role="slider"` and pointer handlers. It means re-implementing keys, clamping and pointer capture, which is where bugs hide.
- A slider library. It adds a dependency, for what the native input already does.

### D2. `AeroCover` with a three-state fallback

Props: `src?: string | null`, `alt: string` and `size` (px, default 64). The root is a fixed-size box (`data-aero="cover"`), so nothing moves when the image arrives. The component tracks three states:

| State | When | Rendered |
|-------|------|----------|
| `none` / `failed` | No `src`, or the image's `error` event fired | Fallback only, no `<img>` |
| `loading` | `src` set, image not loaded yet | Fallback, with the `<img>` on top at `opacity: 0` |
| `loaded` | `load` event fired | `<img>` only |

- **Fallback:** a `div` with `data-aero="cover-fallback"` and `aria-hidden="true"`. It holds a sky gradient from the theme tokens and the "note" icon (D4). It makes no network request.
- **Change of `src`:** resets the state to `loading`.
- **Image already loaded:** `onMounted` checks `img.complete && img.naturalWidth > 0`, in case the image finished before the listener was attached.
- **Image:** `object-fit: cover`, `alt` passed through. A cover with an image must have a non-empty `alt`; the bar passes "<title> cover".

*Alternative rejected:* the fallback as a CSS `background` behind the `<img>`. A broken `<img>` would stay in the DOM with its broken-image icon and naturalWidth 0, which the spec forbids.

### D3. `AeroPlayerBar` composition and API

The bar is built from existing parts:

- the root is an `AeroGlassPanel` with an inner `data-aero="player-bar"`;
- `AeroCover`, then the title and the artist;
- `AeroButton` orbs at the default 44 px size for "Previous" and "Next", with icons;
- a large `AeroButton` orb (D5) for "Play"/"Pause", between them, as in WMP 11;
- `AeroSlider` for "Seek" and "Volume".

**Props:**

- `title` and `artist` (strings, may be empty);
- `coverSrc`;
- `playing`;
- `position` and `duration`, in seconds;
- `volume`, from 0 to 100;
- `previousDisabled`, `playDisabled`, `nextDisabled` and `seekDisabled`.

**Events:**

- `update:position` and `update:volume` (`v-model:position`, `v-model:volume`);
- `toggle-play`;
- `previous` and `next`.

The bar never changes `playing` itself. The parent owns the state: the styleguide in local refs now, T-06 later.

**Details:**

- **Play/Pause:** the accessible name is "Play" or "Pause" from `playing`, with the matching icon. No `<audio>` or `<video>` anywhere.
- **Times:**
  - Seek runs from 0 to `duration` with step 1.
  - The elapsed and total times are formatted as `m:ss`, or `h:mm:ss` from one hour, by a small pure function in `resources/js/lib/formatTime.ts`, which T-06 reuses.
  - Seek's `aria-valuetext` is "<elapsed> of <total>".
- **Text:**
  - Title and artist are single-line blocks with `white-space: nowrap`, `overflow: hidden` and `text-overflow: ellipsis`.
  - The full text is in `title`.
  - Each line keeps a `min-height` of one line, so an empty string leaves the gap with no placeholder.
  - The meta column is `minmax(0, 1fr)`, so long text shrinks instead of widening the page.
- **Notice area:** two slots inside the bar, side by side.
  - **`notice`:** the message only, in a `role="status"` region with `aria-live="polite"`. The region is always in the DOM, so a message that appears later is announced.
  - **`notice-actions`:** the optional `AeroButton` and the optional link, outside the status region, so screen readers do not read the actions as part of the message.
  - The parent supplies both; T-23 owns the texts.
  - A link in `notice-actions` needs a touch area of at least 44x44 px, because the existing mobile test checks every `a[href]`. The bar's scoped CSS gives links in that slot `display: inline-flex`, `align-items: center` and `min-height`/`min-width` of `--aero-touch-min`, through `:slotted(a)`.
- **Extra controls:** an `extra` slot placed after the volume group. Its buttons are expected to be `AeroButton`, so they inherit the touch area and focus rules. The styleguide shows one sample toggle with `aria-pressed`.
- **Ids:** the bar builds its ids with `useId()`, so several bars on one page (the styleguide has two) do not collide.
- **Layout:**
  - At 640 px and wider, one row: cover and meta, transport, then seek with its times, volume and the extras.
  - Below 640 px, the bar wraps:
    - row 1: cover, meta and transport;
    - row 2: seek with its times;
    - row 3, only if row 2 cannot fit them: volume and the extras.

    About 343 px are usable at 375 px, and seek, two times, volume and an extra button may not fit together. The owner judges the final split at the visual validation.
  - Tailwind for the grid, scoped CSS for the look (aero-design D1).

*Alternatives rejected:*

- **A bar that holds its own playing state.** T-06 must drive it from the SDK state; two sources of truth would drift.
- **Separate `AeroPlayerNotice` component.** Two slots are enough until T-23 shows a second need.
- **A `notice` string prop.** T-23 needs a button and a link next to the message.
- **One notice slot holding the message and the actions in the status region.** Screen readers would read "Play here" and "Reconnect Spotify" as part of every message.
- **Rect buttons for "Previous" and "Next".** A rect icon button is about 58 px wide, wider than the large orb, so "Play" would not stand out.

### D4. Icons as hand-made SVG files rendered inline

Six hand-made SVG files in `resources/aero/icons/`:

- `previous.svg`, `play.svg`, `pause.svg` and `next.svg`;
- `volume.svg`;
- `note.svg`, for the cover fallback.

Each file is simple geometry on a 24x24 `viewBox` with `fill="currentColor"`, and has its own row in `CREDITS.md` ("Hand-made for this project").

A small `AeroIcon.vue` (prop `name`) loads the files with `import.meta.glob('…/aero/icons/*.svg', { query: '?raw', import: 'default', eager: true })`. It renders the chosen file through `v-html` inside a `<span aria-hidden="true" data-aero="icon" data-icon="<name>">`. The button around the icon carries the accessible name; `data-icon` lets tests tell which icon is shown.

`v-html` here only renders repository files, never user data.

*Alternatives rejected:*

- **`<img src>`.** It cannot take `currentColor`, so the icon would ignore the gel's text colour and future themes.
- **CSS `mask-image`.** It works, but makes each icon a CSS rule per name and is harder to read.
- **An icon library such as lucide.** The kit ships one, but its flat style is not Aero, and T-25 retires the kit's look.

The owner chooses the final icons, as with the other T-03 assets. These hand-made icons are a first proposal and can be swapped at the visual validation without changing the API.

### D5. `AeroButton` gets a `size` prop

`size?: 'md' | 'lg'`, rendered as `data-size`, default `md`. For the orb, `lg` sets width and height to a new fixed token, the large orb size (about 56 px). The rect shape ignores `size`.

*Alternative rejected:* the bar overriding the orb size from its own scoped CSS. It reaches into another component's internals, and the large orb belongs to the design system: T-16 or a mini-player can reuse it.

### D6. Tokens

The new tokens follow aero-design D2:

- **Fixed tokens in `tokens.css`:**
  - the large orb size;
  - the cover size in the bar;
  - the slider track height.
- **Theme tokens in `themes/sky.css`:**
  - the track groove;
  - the track fill, from `--aero-water`;
  - the thumb gel.

Existing tokens are reused where they fit: `--aero-gel-*`, `--aero-field-*`, `--aero-sky-*` and `--aero-water`. The final list is settled during implementation, and the skill test keeps `references/tokens.md` equal to the CSS.

### D7. Sample cover and provenance

The "With image" cover sample needs a real image served by the app. A hand-made square SVG, `resources/aero/samples/cover.svg`, is imported by `Styleguide.vue` through Vite.

`AeroAssetsTest` adds `samples` to its folders, and `CREDITS.md` gets a row for the file, following the spec "Asset provenance".

The "Broken image" sample points at `/aero/missing-cover.png`, a path the app answers with 404. That is a real failure without request blocking.

*Alternatives rejected:*

- **Reusing `backgrounds/sky.svg`.** T-25 will replace it with a photo, which would silently break this sample.
- **A data URI.** It would not exercise a real network load.

### D8. Styleguide sections

`Styleguide.vue` gains three `AeroGlassPanel` sections after "Checkbox", with stable ids for tests:

- **"Sliders":** "Volume" at 50, "Seek" at 30 and a disabled slider, each 0–100 with step 1 and showing its value.
- **"Cover":** "With image", "No image" and "Broken image", each 96 px.
- **"Player bar":** two bars.
  1. **Interactive:** the sample cover (D7), title "Song A", artist "Artist A", 10 s of 180 s, volume 70.
     - It has a "Toggle text" button that switches between empty and filled title and artist, for the height test.
     - It has a "Long title" button that sets a 120-character title.
     - It has a "Long song" button that sets the position to 0 s and the duration to 3725 s.
  2. **Disabled and notice:** no cover image, so the fallback shows inside the bar; title "Song B" and artist "Artist B". "Previous", "Play", "Next" and "Seek" are disabled.
     - The notice reads "Playing on Kitchen Speaker".
     - Its actions are an `AeroButton` "Play here" and a link "Reconnect Spotify" (`href="#"`).
     - It carries the sample "Extra" toggle in the extra slot.

Sample state lives in local refs. Pressing "Play" only flips `playing`.

### D9. Tests

**Pointer input.** The plugin's `click()` always clicks the centre of an element and takes no position, so "click near the end" cannot be automated. Pointer scenarios use `drag(from, to)` instead:

- **from:** the slider. The press lands in the middle of the track.
- **to:** an element placed to the right of the track, such as the slider's value label, so the pointer moves past the end.

Playwright's drag does not wait for the element to be enabled, so the same call works on disabled sliders. If it does wait, the disabled scenarios fall back to checking `disabled`, the Tab walk and an unchanged value after the keys. Record that in "Manual checks".

**Section headings.** `tests/Feature/StyleguideTest.php` uses `withoutVite()` and does not render the page. Today no test checks the scenario "the page shows the sections". A new browser test, `tests/Browser/StyleguideSectionsTest.php`, reads the heading of each of the seven sections by its id.

**Browser tests**, one file per component, located by `data-aero` and by the section ids:

- `tests/Browser/AeroSliderTest.php`: keyboard, Home and End, dragging past the end, limits and disabled.
- `tests/Browser/AeroCoverTest.php`: no image, 404, valid image and equal sizes.
- `tests/Browser/AeroPlayerBarTest.php`:
  - the controls, "Play" inside the glass panel and the large orb;
  - play and pause with no `<audio>` or `<video>`;
  - the times, the seek valuetext and seeking with the keyboard;
  - empty text and the height;
  - disabled controls;
  - the notice and the extra slot;
  - the long title at 375 px.

**Existing tests extended:**

- `AeroAccessibilityTest`: a reduced-motion test that presses "Play" and moves the sliders. The focus walk, 44x44 px touch area and 375 px tests already cover the new controls.
- `AeroSkillTest`: the three new component paths and the classic media players section's link.
- `AeroAssetsTest`: the `samples` folder.

**New source test.** `tests/Feature/AeroSliderSourceTest.php` reads `AeroSlider.vue` and checks that its range pseudo-element rules (`::-webkit-slider-*`, `::-moz-range-*`) declare no `transition` or `animation`. The browser cannot read the computed style of these pseudo-elements, so the reduced-motion browser test cannot see them.

**`formatTime`:** there is no JavaScript unit runner, and adding one (Vitest) for one function is not worth it. Its cases are checked through the bar's rendered labels in the browser test: "0:10", "3:00", "0:11", and "1:02:05" with the "Long song" sample.

### D10. Skill update

- **`SKILL.md`:**
  - the components table gains AeroSlider, AeroCover, AeroPlayerBar and AeroIcon;
  - "Classic media players" names AeroPlayerBar and AeroSlider and links to `references/components.md`.
- **`references/components.md`:** props, events, slots and examples of the new components.
- **`references/tokens.md`:** the new tokens.
- **`references/traits.md`:** the player bar as the reference for the "classic media players" trait.

## Risks / Trade-offs

- **[Range pseudo-elements differ per engine]** → Webkit and Firefox are styled separately (D1). Chromium is tested automatically, and the owner checks the phone browser by hand at the visual validation.
- **[The global reduced-motion rule does not reach `::-webkit-slider-thumb`]** → no transitions on the thumb at all (D1), checked by the source test (D9), because the browser test cannot read the thumb's computed style.
- **[Hand-made icons and sample cover may look amateur]** → the owner rejected the hand-made SVG background in T-03. The icons are simple glyphs, a different case, but the owner reviews them at the visual validation and can swap them for chosen files without any API change (D4).
- **[`v-html` for icons]** → only static repository files reach it, loaded at build time; no runtime or user input.
- **[The drag starts at the centre of the slider]** → the press first sets the value near the middle, then the move past the end sets the maximum. The scenario only checks the final value, so it holds whatever the engine does on the press.

## Migration Plan

Additive frontend change. No data, routes or configuration. Rollback is reverting the commits.

## Open Questions

- The final icon set and the sample cover can be swapped by the owner at the visual validation (D4, D7). This does not change specs or tasks.

## Backlog follow-ups (applied at /ship)

- **T-06:** the bar API from D3:
  - props and events;
  - `v-model:position` in seconds;
  - `formatTime`;
  - the per-control disabled props.

  The persistent layout and the real volume effect stay in T-06.
- **T-23:** the `notice` slot, inside a `role="status"` region, and the `notice-actions` slot outside it. T-23 supplies the messages and the actions.
- **T-13, T-16 and T-17:** the `extra` slot; their buttons are `AeroButton` with `aria-pressed` or `aria-expanded`.
- **T-07 and T-08:** use `AeroCover` with `src` set to `null` when a track has no artwork.

## Manual checks

<!-- Filled during /opsx-apply: any automated scenario that falls back to a manual or reduced check (D9), and the slider on the owner's phone browser (tasks 7.1). -->

## Validación visual

<!-- Filled during /opsx-apply: date, screenshots at 1280 and 375 px of "Sliders", "Cover" and "Player bar", one verdict per trait, and each requested correction. -->
