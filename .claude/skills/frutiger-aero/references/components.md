# Aero base components

All live in `resources/js/components/aero/`. Each root element has a `data-aero` hook for tests. All of them are shown on `/styleguide`.

## AeroButton (`resources/js/components/aero/AeroButton.vue`)

- Hook: `data-aero="button"`, plus `data-shape` and, for forced looks, `data-state`.
- Props: `shape: 'rect' | 'orb'` (default `rect`), `type: 'button' | 'submit'` (default `button`), `disabled`, `state?: 'hover' | 'pressed'`.
- `state` forces a look for static samples only. Real hover sits inside `@media (hover: hover)` so a tap never leaves hover stuck; pressed is `:active`, which clears when the mouse is released outside.
- `disabled` uses the native attribute: no clicks, no Tab stop.
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
