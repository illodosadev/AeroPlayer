# Aero base components

All live in `resources/js/components/aero/`. Each root element has a `data-aero` hook for tests. All of them are shown on `/styleguide`.

## AeroButton (`resources/js/components/aero/AeroButton.vue`)

- Hook: `data-aero="button"`, plus `data-shape` and, for forced looks, `data-state`.
- Props: `shape: 'rect' | 'orb'` (default `rect`), `type: 'button' | 'submit'` (default `button`), `disabled`, `state?: 'hover' | 'pressed'`.
- `state` forces a look for static samples only. Real hover sits inside `@media (hover: hover)` so a tap never leaves hover stuck; pressed is `:active`, which clears when the mouse is released outside.
- `disabled` uses the native attribute: no clicks, no Tab stop.
- `size: 'md' | 'lg'` (default `md`, as `data-size`): `lg` makes the orb `--aero-orb-size-lg` wide, for "Play" in the player bar. The rectangle ignores it.
- The orb is 44x44 px; give it an `aria-label` when it has no text.

```vue
<AeroButton type="submit">Log in</AeroButton>
<AeroButton shape="orb" aria-label="Play">▶</AeroButton>
```

## AeroGlassPanel (`resources/js/components/aero/AeroGlassPanel.vue`)

- Hook: `data-aero="glass-panel"`. Renders a `<section>` with a slot.
- Translucent with blur where `backdrop-filter` is supported; otherwise, or with `aero-no-blur` on `<html>`, it uses `--aero-panel-bg-fallback` and no blur.

## AeroInput (`resources/js/components/aero/AeroInput.vue`)

- Hook: `data-aero="input"` on the wrapper; the `<input>` gets the `id`.
- Props: `id`, `label`, `modelValue` (use `v-model`), `type`, `error`, `autocomplete`, `placeholder`, `required`, `autofocus`, `tabindex`.
- With `error`, the field gets `aria-invalid="true"` and `aria-describedby` pointing at the message (`<id>-error`).
- Browser autofill keeps the Aero field colours.

```vue
<AeroInput id="email" v-model="form.email" label="Email" type="email" :error="form.errors.email" />
```

## AeroCheckbox (`resources/js/components/aero/AeroCheckbox.vue`)

- Hook: `data-aero="checkbox"` on the `<label>`; the native `<input type="checkbox">` gets the `id`.
- Props: `id`, `modelValue` (use `v-model` with a boolean). The slot is the label text.
- The native input stays focusable and posts with the form; the Aero box is drawn next to it and shows the focus outline. The label keeps a 44x44 px area.

```vue
<AeroCheckbox id="remember" v-model="form.remember">Remember me</AeroCheckbox>
```

## AeroBackground (`resources/js/components/aero/AeroBackground.vue`)

- Hook: `data-aero="background"`. Full-screen sky layer with decorative bubbles and a slot for the page.
- Paints `--aero-background-image` over `--aero-background-fallback`, so the gradient shows while the image loads or if it fails.
- Use it once per page, as the outermost element (the login layout and `/styleguide` do).

## AeroSlider (`resources/js/components/aero/AeroSlider.vue`)

- Hook: `data-aero="slider"` on the native `<input type="range">`, which also gets the `id`.
- Props: `modelValue` (use `v-model` with a number), `label` (the accessible name, rendered as `aria-label`), `min` (default 0), `max` (default 100), `step` (default 1), `disabled`, `valueText` (optional `aria-valuetext`, for example "0:10 of 3:00").
- The browser gives the keyboard (arrows, Home, End, Page keys), the `slider` role, the accessible value, clamping to the limits and pointer capture while dragging past the track.
- Look: an inset groove (`--aero-slider-track`) with the filled part (`--aero-slider-fill`) and a small gel ball as the thumb (`--aero-slider-thumb`). Webkit and Firefox pseudo-elements are styled separately.
- The input is 44 px tall and fills its container, so the touch area is 44x44 px or more while the groove is thinner.
- Never put a `transition` or `animation` on the range pseudo-elements: the global reduced-motion rule does not reach them (`tests/Feature/AeroSliderSourceTest.php`).
- `disabled` uses the native attribute: no pointer or keyboard input, no Tab stop.

```vue
<AeroSlider id="volume" v-model="volume" label="Volume" />
<AeroSlider v-model="position" label="Seek" :max="duration" :value-text="`${formatTime(position)} of ${formatTime(duration)}`" />
```

## AeroCover (`resources/js/components/aero/AeroCover.vue`)

- Hook: `data-aero="cover"` on a fixed-size box, with `data-state` (`none`, `loading`, `loaded` or `failed`).
- Props: `src?: string | null`, `alt` (required; a cover with an image needs a non-empty text, for example "<title> cover"), `size?: number` in px (default 64; a parent can also set `--_size` on the cover from its own scoped CSS).
- With no `src`, while the image loads, or when it fails, it shows the generic Aero cover: `data-aero="cover-fallback"`, `aria-hidden="true"`, a sky gradient from the theme tokens and the "note" icon. It makes no network request.
- While loading, the `<img>` sits over the fallback at opacity 0; when it fails, the `<img>` is removed, so no broken-image icon is ever shown. The box never changes size.
- Pass `src: null` when a track has no artwork (T-07, T-08).

```vue
<AeroCover :src="track.image" :alt="`${track.title} cover`" :size="96" />
```

## AeroIcon (`resources/js/components/aero/AeroIcon.vue`)

- Hook: `data-aero="icon"`, plus `data-icon` with the icon name, on a `<span aria-hidden="true">`.
- Prop: `name`: `previous`, `play`, `pause`, `next`, `volume` or `note`, the hand-made files in `resources/aero/icons/` (each logged in `resources/aero/CREDITS.md`).
- The SVG is inlined and uses `currentColor`, so it takes the text colour of the button around it. The icon is decorative: the button carries the accessible name (`aria-label`).
- To add an icon, drop a 24x24 SVG with `fill="currentColor"` in `resources/aero/icons/`, add its provenance row and its name to the `AeroIconName` type.

```vue
<AeroButton shape="orb" aria-label="Next"><AeroIcon name="next" /></AeroButton>
```

## AeroPlayerBar (`resources/js/components/aero/AeroPlayerBar.vue`)

- Hook: `data-aero="player-bar"`, inside an AeroGlassPanel (a thinner strip). Visual only: it emits events and never creates `<audio>` or `<video>`. The parent owns the state.
- Props: `title`, `artist` (may be empty: each line keeps its height, with no placeholder), `coverSrc` (`null` shows the cover fallback), `playing`, `position` and `duration` in seconds, `volume` (0–100), and `previousDisabled`, `playDisabled`, `nextDisabled`, `seekDisabled`.
- Events: `update:position` and `update:volume` (use `v-model:position` and `v-model:volume`), `toggle-play`, `previous`, `next`. The bar never flips `playing` itself.
- "Play" is a large orb (`size="lg"`) between the "Previous" and "Next" orbs; its accessible name and icon follow `playing` ("Play"/"Pause").
- Times are `m:ss`, or `h:mm:ss` from one hour (`resources/js/lib/formatTime.ts`); "Seek" has `aria-valuetext` "<elapsed> of <total>". Hooks: `data-time="elapsed"`, `data-time="total"`, `data-part="title"`, `data-part="artist"`.
- Title and artist are one line cut with an ellipsis; the full text is in their `title` attribute.
- Slots:
  - `notice`: the message only, inside a `role="status"` `aria-live="polite"` region that is always rendered, so a later message is announced;
  - `notice-actions`: an optional AeroButton and an optional link, outside the status region; links get a 44x44 px area;
  - `extra`: more AeroButtons from other features (shuffle, repeat, queue, visualizer, lyrics), with `aria-pressed` or `aria-expanded`.
- Layout: one row from 640 px when it fits; below, it wraps into song and transport, seek, then volume and extras.

```vue
<AeroPlayerBar
    v-model:position="position"
    v-model:volume="volume"
    :title="track.title"
    :artist="track.artist"
    :cover-src="track.image"
    :playing="playing"
    :duration="track.duration"
    @toggle-play="toggle"
>
    <template #notice>Playing on Kitchen Speaker</template>
    <template #notice-actions>
        <AeroButton>Play here</AeroButton>
        <a href="/spotify/connect">Reconnect Spotify</a>
    </template>
</AeroPlayerBar>
```
