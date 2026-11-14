---
name: frutiger-aero
description: AeroPlayer's Frutiger Aero design system. Use it whenever you create or change the app's interface (Vue pages, layouts, components or CSS) so new screens reuse the Aero tokens, components and accessibility rules instead of inventing their own look.
---

# Frutiger Aero for AeroPlayer

AeroPlayer looks like a 2000s media player: glossy gel buttons, Aero Glass panels, sky and water, and real-feeling relief. This skill is the map; the detail lives in `references/`.

Adapted from the community skill `frutiger-aero` in `sickn33/agentic-awesome-skills` (MIT licence). Only the parts that fit this Vue app are kept; classic players and accessibility are added.

## Before you write UI

1. Read `references/traits.md` for the look you are aiming at.
2. Reuse a base component from the table below before writing new markup.
3. Use only the tokens in `references/tokens.md`. Never write a colour, gradient or shadow that a theme should change as a literal.
4. Check every rule in `references/accessibility.md` before you finish.
5. Show any new component and its states on `/styleguide` (`resources/js/pages/Styleguide.vue`) and cover it with a browser test in `tests/Browser`.

## The four traits

- **Glossy and glass:** gel buttons with a bright top half, translucent panels with a soft reflection.
- **Nature and sky:** sky gradients, clouds, water, bubbles, green hills.
- **Classic media players:** WMP 11, iTunes 7–9, Zune and Winamp: round play orbs, dense lists, glowing transport bars.
- **Skeuomorphism:** relief, inner shadows, highlights and objects that look pressable.

Details and do/don't lists: `references/traits.md`.

## Base components

| Component | File | Hook |
|-----------|------|------|
| AeroButton (rectangle and orb) | `resources/js/components/aero/AeroButton.vue` | `data-aero="button"` |
| AeroGlassPanel | `resources/js/components/aero/AeroGlassPanel.vue` | `data-aero="glass-panel"` |
| AeroInput | `resources/js/components/aero/AeroInput.vue` | `data-aero="input"` |
| AeroCheckbox | `resources/js/components/aero/AeroCheckbox.vue` | `data-aero="checkbox"` |
| AeroBackground | `resources/js/components/aero/AeroBackground.vue` | `data-aero="background"` |
| AeroSlider | `resources/js/components/aero/AeroSlider.vue` | `data-aero="slider"` |
| AeroCover | `resources/js/components/aero/AeroCover.vue` | `data-aero="cover"`, fallback `data-aero="cover-fallback"` |
| AeroIcon | `resources/js/components/aero/AeroIcon.vue` | `data-aero="icon"`, `data-icon="<name>"` |
| AeroPlayerBar | `resources/js/components/aero/AeroPlayerBar.vue` | `data-aero="player-bar"` (inside an AeroGlassPanel) |

Props, states and examples: `references/components.md`.

## Classic media players

The player controls carry this trait: a glass strip with a cover, a large "Play" orb between smaller "Previous" and "Next" orbs, and inset grooves for "Seek" and "Volume". Build player UI from AeroPlayerBar (`resources/js/components/aero/AeroPlayerBar.vue`) and AeroSlider (`resources/js/components/aero/AeroSlider.vue`) instead of new markup. Their props, events and slots are in [references/components.md](references/components.md).

## Where the CSS lives

- `resources/css/aero/tokens.css`: fixed tokens (sizes, type, blur, focus, touch size) on `:root`.
- `resources/css/aero/themes/sky.css`: the default "sky" theme under `:where(:root), [data-theme="sky"]`. `<html>` carries `data-theme="sky"`.
- `resources/css/aero/base.css`: the display and Selawik fonts, the global focus outline, the reduced-motion rule and the forced no-blur fallback.
- The display font file (`public/fonts/aero-display.woff2`) is a local copy that is not versioned; without it, the text uses the rest of the font stack. Never add it to git.
- Component look goes in each component's `<style scoped>`. Tailwind is for layout only (flex, grid, spacing, breakpoints).
- Components only **read** `--aero-*` tokens. A local variable inside a component uses another prefix, such as `--_base`.
- A new theme (T-18) is a new file that redefines the theme tokens under `[data-theme="<name>"]`. Never use the `.dark` class: the app is light only.

## Accessibility in one breath

Visible 2 px focus outline on every control, 4.5:1 text contrast, no animation with reduced motion, an almost opaque fallback where backdrop blur is missing, and 44x44 px touch areas. Details: `references/accessibility.md`.

## Testing hooks

- Locate components by their `data-aero` hook, never by CSS classes.
- Browser tests use the helpers in `tests/Pest.php`:
  - `signedInOwnerVisit()` and `aeroContrastRatio()`;
  - `aeroAssertFocusWalk($page, '#page-title')` for "Visible keyboard focus", which only counts enabled controls;
  - `$page->resize(375, 667)->script(AERO_MOBILE_PROBLEMS)`, which must return `[]`, for "Mobile layout".
- A new Aero page gets the focus walk and the 375 px check in its own browser test.
- In Pest browser tests, use explicit selectors (`#id`, `.class`, `[attr]`). A bare tag such as `h1` is looked up as text and waits until the timeout.

## Keep this skill in sync

`tests/Feature/AeroSkillTest.php` fails when a token is declared in the CSS but missing here, when this skill names a token that does not exist, or when a component file declares an `--aero-*` token. Update `references/tokens.md` in the same change as the CSS.
