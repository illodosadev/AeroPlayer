# Aero accessibility rules

Each rule has a browser test in `tests/Browser`; keep them passing when you add UI.

## Visible focus

- Every enabled control is reachable with Tab, in DOM order. Do not set positive `tabindex` values.
- The focused control shows a 2 px outline (`--aero-focus-width`, `--aero-focus-color`) that it does not have unfocused. `base.css` sets it on `:focus-visible`; a component that hides its native control (like the checkbox) draws the outline on the visible part.

## Contrast

- Text has a contrast of at least 4.5:1 (WCAG AA) with the panel or field behind it.
- Automated checks measure contrast on the no-blur fallback, because a translucent panel over an image has no single colour. The owner checks the real glass by eye.

## Reduced motion

- With `prefers-reduced-motion: reduce`, nothing animates and transitions last 0.01 s at most. `base.css` enforces this globally; do not override it with `!important`.

## No-blur fallback

- Where `backdrop-filter` is missing, or with the class `aero-no-blur` on `<html>`, glass panels use `--aero-panel-bg-fallback` (alpha at least 0.85) and no blur.

## Touch targets

- Every interactive control has a touch area of at least 44x44 px (`--aero-touch-min`). The area may include padding or the label.
- At 375 px wide, pages fit without horizontal scrolling and every control stays inside the viewport.

## Light mode only

- The app never uses the `.dark` class; Aero components ignore it. Dark looks, if any, come as themes with `data-theme` (T-18).
