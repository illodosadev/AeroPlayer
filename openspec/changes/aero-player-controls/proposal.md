# Proposal

Implements backlog task T-21 (controles del reproductor Aero).

## Why

The player bar is the most visible part of AeroPlayer and carries the discovery's "classic media players" trait (WMP 11, iTunes 7–9, Zune, Winamp). T-03 left base Aero components but none for playback. T-06 needs a bar to wire to Spotify, T-07 and T-08 a generic cover for songs without artwork, T-23 a notice area, and T-13, T-16 and T-17 room for their buttons. Building the controls now, visual only, keeps T-06 focused on playback.

## What Changes

- `AeroSlider`: a native `<input type="range">` with an Aero track and a glossy thumb. Keyboard, pointer and limits come from the browser. It has a disabled state and a 44x44 px touch area.
- `AeroCover`: the song's image at a fixed size. With no image, while it loads, or when it fails, it shows a generic Aero cover (`data-aero="cover-fallback"`) drawn without network requests.
- `AeroPlayerBar`, inside an `AeroGlassPanel`. It is visual only: it emits events and plays no sound.
  - a cover, a title and an artist;
  - "Previous" and "Next" orbs, with a larger "Play"/"Pause" orb between them;
  - "Seek" with elapsed and total time, and "Volume";
  - a disabled state for each control;
  - a notice area: a message, plus optional actions (a button and a link);
  - a slot for extra buttons.
- `AeroButton` gains a `size` prop for the larger orb.
- `AeroIcon` renders hand-made SVG icons (previous, play, pause, next, volume, note) from `resources/aero/icons/`, each with a provenance row.
- `/styleguide` gains the sections "Sliders", "Cover" and "Player bar". The cover sample is a hand-made image in `resources/aero/samples/`, which joins the provenance log.
- The `frutiger-aero` skill documents the new components and tokens.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `aero-design`:
  - new requirements "Aero slider", "Aero cover with fallback" and the "Aero player bar" group;
  - "Styleguide page" gains three sections;
  - "Aero style skill" names the new components;
  - "Asset provenance" covers sample images;
  - "Reduced motion" covers pressing "Play" and moving the sliders.

## Non-goals

- Real playback, the bar's data, the seek range in seconds and the volume's real effect, including on iOS, where the web cannot set it (T-06).
- The persistent Inertia layout that hosts the bar (T-06).
- Shuffle, repeat and queue (T-13), "Visualizer" (T-16) and "Lyrics" (T-17). Only the slot is added here.
- Notice texts and when they appear (T-23).
- Themes (T-18) and migrating the kit's screens (T-25).

## Impact

- **Frontend:**
  - `AeroSlider.vue`, `AeroCover.vue`, `AeroPlayerBar.vue` and `AeroIcon.vue` in `resources/js/components/aero/`;
  - `resources/js/lib/formatTime.ts`;
  - a `size` prop in `AeroButton.vue`;
  - new sections in `Styleguide.vue`;
  - possibly new `--aero-*` tokens.
- **Assets:** `resources/aero/icons/*.svg` and `resources/aero/samples/cover.svg`, with rows in `CREDITS.md`.
- **Tests:**
  - new browser tests;
  - `AeroSkillTest` and `AeroAssetsTest` extended.
- **Skill:** `SKILL.md` and `references/` of `.claude/skills/frutiger-aero`.
- **Backend:** none.
