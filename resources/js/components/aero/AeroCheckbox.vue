<script setup lang="ts">
// Native checkbox inside a label, so the keyboard, form posts and assistive tech work without extra code (design D3).
// The Aero box is drawn over the hidden native input. The label keeps a 44 px touch area.
defineProps<{
    id: string;
    modelValue?: boolean;
}>();

defineEmits<{
    'update:modelValue': [value: boolean];
}>();
</script>

<template>
    <label data-aero="checkbox" class="aero-checkbox" :for="id">
        <input
            :id="id"
            class="aero-checkbox__input"
            type="checkbox"
            :checked="modelValue"
            @change="$emit('update:modelValue', ($event.target as HTMLInputElement).checked)"
        />
        <span class="aero-checkbox__box" aria-hidden="true" />
        <span class="aero-checkbox__text"><slot /></span>
    </label>
</template>

<style scoped>
.aero-checkbox {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: var(--aero-space-2);
    min-width: var(--aero-touch-min);
    min-height: var(--aero-touch-min);
    padding: var(--aero-space-2) 0;
    color: var(--aero-text);
    font-family: var(--aero-font-family);
    cursor: pointer;
}

.aero-checkbox__input {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: 0;
    opacity: 0;
}

.aero-checkbox__box {
    position: relative;
    flex: none;
    width: 22px;
    height: 22px;
    border: 1px solid var(--aero-field-border);
    border-radius: var(--aero-radius-sm);
    background: var(--aero-field-bg);
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.15);
}

.aero-checkbox__input:checked + .aero-checkbox__box {
    border-color: color-mix(in srgb, var(--aero-gel-base) 70%, #000);
    background: linear-gradient(to bottom, color-mix(in srgb, var(--aero-gel-base) 80%, #fff), var(--aero-gel-base));
}

.aero-checkbox__input:checked + .aero-checkbox__box::after {
    content: '';
    position: absolute;
    left: 6px;
    top: 1px;
    width: 6px;
    height: 12px;
    border: solid #ffffff;
    border-width: 0 3px 3px 0;
    transform: rotate(45deg);
}

.aero-checkbox__input:focus-visible + .aero-checkbox__box {
    outline: var(--aero-focus-width) solid var(--aero-focus-color);
    outline-offset: 2px;
}
</style>
