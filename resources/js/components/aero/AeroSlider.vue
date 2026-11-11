<script setup lang="ts">
// Native range input with the Aero look (design D1): keyboard, the slider role, clamping and pointer capture come
// from the browser. The track is an inset groove (WMP 11 relief) and the thumb a small gel ball. The input itself is
// 44 px tall, so the touch area stays at 44x44 px while the track is drawn thinner.
// No transition or animation on the range pseudo-elements: the global reduced-motion rule does not reach them
// (tests/Feature/AeroSliderSourceTest.php).
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: number;
        label: string;
        min?: number;
        max?: number;
        step?: number;
        disabled?: boolean;
        valueText?: string;
    }>(),
    { min: 0, max: 100, step: 1, disabled: false, valueText: undefined },
);

const emit = defineEmits<{
    'update:modelValue': [value: number];
}>();

const fill = computed(() => {
    const span = props.max - props.min;
    const ratio = span > 0 ? (props.modelValue - props.min) / span : 0;

    return `${Math.min(1, Math.max(0, ratio)) * 100}%`;
});

function onInput(event: Event): void {
    emit('update:modelValue', Number((event.target as HTMLInputElement).value));
}
</script>

<template>
    <input
        data-aero="slider"
        class="aero-slider"
        type="range"
        :value="modelValue"
        :min="min"
        :max="max"
        :step="step"
        :disabled="disabled"
        :aria-label="label"
        :aria-valuetext="valueText"
        :style="{ '--_fill': fill }"
        @input="onInput"
    />
</template>

<style scoped>
.aero-slider {
    --_thumb: 18px;

    display: block;
    width: 100%;
    min-width: var(--aero-touch-min);
    height: var(--aero-touch-min);
    margin: 0;
    padding: 0;
    background: transparent;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
}

.aero-slider:disabled {
    cursor: not-allowed;
    opacity: 0.55;
}

/* Track: an inset groove with the filled part painted over it (Webkit and Blink). */
.aero-slider::-webkit-slider-runnable-track {
    height: var(--aero-slider-track-height);
    border: 1px solid var(--aero-field-border);
    border-radius: var(--aero-radius-pill);
    background:
        var(--aero-slider-fill) 0 0 / var(--_fill) 100% no-repeat,
        var(--aero-slider-track);
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.35);
}

.aero-slider::-webkit-slider-thumb {
    width: var(--_thumb);
    height: var(--_thumb);
    margin-top: calc((var(--aero-slider-track-height) - 2px - var(--_thumb)) / 2);
    border: 1px solid color-mix(in srgb, var(--aero-gel-base) 60%, #000);
    border-radius: 50%;
    background: var(--aero-slider-thumb);
    box-shadow:
        0 1px 3px var(--aero-gel-shadow),
        inset 0 1px 0 rgba(255, 255, 255, 0.8);
    appearance: none;
    -webkit-appearance: none;
}

/* Firefox draws the filled part itself with ::-moz-range-progress. */
.aero-slider::-moz-range-track {
    height: var(--aero-slider-track-height);
    border: 1px solid var(--aero-field-border);
    border-radius: var(--aero-radius-pill);
    background: var(--aero-slider-track);
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.35);
}

.aero-slider::-moz-range-progress {
    height: var(--aero-slider-track-height);
    border-radius: var(--aero-radius-pill);
    background: var(--aero-slider-fill);
}

.aero-slider::-moz-range-thumb {
    width: var(--_thumb);
    height: var(--_thumb);
    border: 1px solid color-mix(in srgb, var(--aero-gel-base) 60%, #000);
    border-radius: 50%;
    background: var(--aero-slider-thumb);
    box-shadow:
        0 1px 3px var(--aero-gel-shadow),
        inset 0 1px 0 rgba(255, 255, 255, 0.8);
}

.aero-slider:disabled::-webkit-slider-thumb {
    filter: grayscale(1);
}

.aero-slider:disabled::-moz-range-thumb {
    filter: grayscale(1);
}
</style>
