<script setup lang="ts">
// Glossy button in two shapes: a rectangle and a round orb (design D3). The forced "state" prop lets the styleguide
// show hover and pressed looks side by side; real hover and press use the same rules.
withDefaults(
    defineProps<{
        shape?: 'rect' | 'orb';
        type?: 'button' | 'submit';
        disabled?: boolean;
        state?: 'hover' | 'pressed';
    }>(),
    { shape: 'rect', type: 'button', disabled: false, state: undefined },
);
</script>

<template>
    <button
        data-aero="button"
        :data-shape="shape"
        :data-state="state"
        :type="type"
        :disabled="disabled"
        :class="['aero-button', shape === 'orb' ? 'aero-button--orb' : 'aero-button--rect']"
    >
        <slot />
    </button>
</template>

<style scoped>
.aero-button {
    --_base: var(--aero-gel-base);
    --_low: color-mix(in srgb, var(--aero-gel-base) 70%, #000);
    --_top: var(--aero-gel-highlight);

    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: var(--aero-touch-min);
    min-width: var(--aero-touch-min);
    padding: var(--aero-space-2) var(--aero-space-4);
    border: 1px solid color-mix(in srgb, var(--_base) 60%, #000);
    border-radius: var(--aero-radius-pill);
    color: #ffffff;
    font-family: var(--aero-font-family);
    font-size: var(--aero-font-size-body);
    text-shadow: 0 1px 1px rgba(0, 0, 0, 0.35);
    background-image:
        linear-gradient(to bottom, var(--_top) 0%, rgba(255, 255, 255, 0) 50%),
        linear-gradient(to bottom, color-mix(in srgb, var(--_base) 85%, #fff) 0%, var(--_base) 50%, var(--_low) 100%);
    box-shadow: 0 2px 4px var(--aero-gel-shadow), inset 0 1px 0 rgba(255, 255, 255, 0.6);
    cursor: pointer;
    transition: background-color 0.15s ease, box-shadow 0.15s ease, transform 0.05s ease;
}

.aero-button--orb {
    width: var(--aero-touch-min);
    height: var(--aero-touch-min);
    padding: 0;
    border-radius: 50%;
}

/* Real hover only where the pointer really hovers, so a tap does not leave hover stuck (design D3). */
@media (hover: hover) {
    .aero-button:not(:disabled):hover {
        --_base: color-mix(in srgb, var(--aero-gel-base) 78%, #fff);
        --_top: rgba(255, 255, 255, 0.95);
        box-shadow: 0 2px 6px var(--aero-gel-shadow), 0 0 0 3px rgba(255, 255, 255, 0.35), inset 0 1px 0 #fff;
    }
}

/* Forced looks for the styleguide samples. */
.aero-button[data-state='hover'] {
    --_base: color-mix(in srgb, var(--aero-gel-base) 78%, #fff);
    --_top: rgba(255, 255, 255, 0.95);
    box-shadow: 0 2px 6px var(--aero-gel-shadow), 0 0 0 3px rgba(255, 255, 255, 0.35), inset 0 1px 0 #fff;
}

/* Pressed: the button sinks 1 px and darkens. :active clears on release outside, so it does not stick. */
.aero-button:not(:disabled):active,
.aero-button[data-state='pressed'] {
    --_base: color-mix(in srgb, var(--aero-gel-base) 80%, #000);
    --_top: rgba(255, 255, 255, 0.45);
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.35);
    transform: translateY(1px);
}

.aero-button:disabled {
    --_base: #9aa7b3;
    --_low: #7c8a97;
    --_top: rgba(255, 255, 255, 0.5);
    color: rgba(255, 255, 255, 0.85);
    cursor: not-allowed;
    box-shadow: none;
}

.aero-button:focus-visible {
    outline: var(--aero-focus-width) solid var(--aero-focus-color);
    outline-offset: 2px;
}
</style>
