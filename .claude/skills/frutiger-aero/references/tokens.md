# Aero tokens

Every `--aero-*` custom property the app declares, with its role. Components read these and never declare them. A test keeps this list equal to the CSS.

## Fixed tokens (`resources/css/aero/tokens.css`, on `:root`)

| Token | Role |
|-------|------|
| `--aero-radius-sm` | Small corner radius: fields, checkbox box. |
| `--aero-radius-md` | Panel corner radius. |
| `--aero-radius-pill` | Fully rounded buttons. |
| `--aero-space-1` | 0.25rem spacing step. |
| `--aero-space-2` | 0.5rem spacing step. |
| `--aero-space-3` | 0.75rem spacing step. |
| `--aero-space-4` | 1rem spacing step. |
| `--aero-space-6` | 1.5rem spacing step (panel padding). |
| `--aero-font-family` | All text: the local display font (FOT-NewRodin Pro EB), then Segoe UI, Selawik, Frutiger, Myriad Pro and system fonts. |
| `--aero-font-size-body` | Body text size. |
| `--aero-font-size-small` | Labels, hints and error messages. |
| `--aero-font-size-title` | Panel titles. |
| `--aero-blur` | Backdrop blur of the glass (small on purpose). |
| `--aero-focus-width` | Width of the focus outline (2 px). |
| `--aero-touch-min` | Minimum touch area side (44 px). |
| `--aero-orb-size-lg` | Side of the large orb (`AeroButton size="lg"`), used for "Play"/"Pause" in the player bar. |
| `--aero-cover-size-bar` | Side of the cover in the player bar. |
| `--aero-slider-track-height` | Height of the slider groove (the input itself stays 44 px tall). |

## Theme tokens ("sky", `resources/css/aero/themes/sky.css`)

| Token | Role |
|-------|------|
| `--aero-sky-top` | Top colour of the sky. |
| `--aero-sky-horizon` | Pale sky colour near the horizon; background colour under the image. |
| `--aero-grass` | Green of the hill in the fallback gradient. |
| `--aero-water` | Water blue (reserved for the visualizer and player accents). |
| `--aero-text` | Main text colour on panels and fields. |
| `--aero-text-muted` | Secondary text. |
| `--aero-error` | Error text and invalid field border. |
| `--aero-panel-bg` | Translucent glass panel background. |
| `--aero-panel-bg-fallback` | Almost opaque panel background without blur (alpha at least 0.85). |
| `--aero-panel-border` | Light 1 px panel border. |
| `--aero-panel-shadow` | Panel drop shadow. |
| `--aero-gel-base` | Base colour of gel buttons and the checked checkbox. |
| `--aero-gel-highlight` | Top highlight of the gel. |
| `--aero-gel-shadow` | Drop shadow of the gel. |
| `--aero-field-bg` | Field and checkbox background. |
| `--aero-field-border` | Field and checkbox border. |
| `--aero-focus-color` | Focus outline colour. |
| `--aero-slider-track` | Empty part of the slider groove (an image: gradient). |
| `--aero-slider-fill` | Filled part of the slider groove, from `--aero-water` (an image: gradient). |
| `--aero-slider-thumb` | Gel ball of the slider thumb (an image: radial gradient). |
| `--aero-background-image` | Background image layer (`url(...)`), painted over the fallback gradient. |
| `--aero-background-fallback` | Sky gradient shown first and when the image fails. |
