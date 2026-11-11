# The four Aero traits

## Glossy and glass

- Gel buttons: a highlight gradient on the top half over a base gradient that darkens towards the bottom, a thin darker border and a soft drop shadow. Hover brightens the gel and adds a faint white glow; pressed darkens it and sinks it 1 px.
- Glass panels: a translucent white pane with a small blur (about 6 px), a 1 px light border and a reflection on the top half.
- Keep the blur small. A large blur reads as modern glassmorphism, not Vista or Windows 7.

Do: white highlights, rounded pills and orbs, clear borders.
Don't: flat fills, hard black shadows, neon gradients.

## Nature and sky

- Backgrounds are sky gradients (deep blue at the top, pale near the horizon), clouds, a green hill and water.
- Bubbles float slowly upwards as decoration and are hidden from assistive technology.
- The sky gradient is always painted under any image, so the page never shows a blank background.

## Classic media players

References: Windows Media Player 11, iTunes 7–9, Zune and Winamp.

- The play control is a big round orb; secondary transport buttons are smaller orbs or pills.
- Lists are dense, with alternating light rows and a glossy selected row.
- Transport and progress bars glow softly and sit in a glass strip.
- Album art has a slight reflection or a framed edge.

The reference for this trait in the app is AeroPlayerBar (`resources/js/components/aero/AeroPlayerBar.vue`), shown on `/styleguide` in the "Player bar" section: WMP 11's large central play orb, inset seek and volume grooves (AeroSlider) and a framed cover with a reflection (AeroCover).

## Skeuomorphism

- Controls look pressable: highlight on top, shadow below, inner shadow when pressed or when they are fields.
- Fields are slightly inset (`inset` shadow), checkboxes look like small glossy boxes with a white tick.
- Relief is subtle: one highlight and one shadow per element, not stacks of effects.
