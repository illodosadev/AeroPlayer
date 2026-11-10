<script setup lang="ts">
// Text field with its label and error message. The error is linked with aria-describedby and the field is marked
// aria-invalid (design D3). The root carries data-aero="input".
defineProps<{
    id: string;
    label: string;
    modelValue?: string;
    type?: string;
    error?: string;
    autocomplete?: string;
    placeholder?: string;
    required?: boolean;
    autofocus?: boolean;
    tabindex?: number;
}>();

defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <div data-aero="input" class="aero-input">
        <label :for="id" class="aero-input__label">{{ label }}</label>
        <input
            :id="id"
            class="aero-input__field"
            :type="type ?? 'text'"
            :value="modelValue"
            :autocomplete="autocomplete"
            :placeholder="placeholder"
            :required="required"
            :autofocus="autofocus"
            :tabindex="tabindex"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="error ? `${id}-error` : undefined"
            @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        />
        <p v-if="error" :id="`${id}-error`" class="aero-input__error">{{ error }}</p>
    </div>
</template>

<style scoped>
.aero-input {
    display: flex;
    flex-direction: column;
    gap: var(--aero-space-1);
    font-family: var(--aero-font-family);
}

.aero-input__label {
    color: var(--aero-text);
    font-size: var(--aero-font-size-small);
    font-weight: 600;
}

.aero-input__field {
    min-height: var(--aero-touch-min);
    padding: var(--aero-space-2) var(--aero-space-3);
    border: 1px solid var(--aero-field-border);
    border-radius: var(--aero-radius-sm);
    background: var(--aero-field-bg);
    color: var(--aero-text);
    font-family: inherit;
    font-size: var(--aero-font-size-body);
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.12);
}

.aero-input__field:focus-visible {
    outline: var(--aero-focus-width) solid var(--aero-focus-color);
    outline-offset: 1px;
}

.aero-input__field[aria-invalid='true'] {
    border-color: var(--aero-error);
}

/* Browser autofill would paint the field yellow or blue: keep the Aero field colours and text. */
.aero-input__field:-webkit-autofill,
.aero-input__field:-webkit-autofill:focus {
    -webkit-box-shadow: 0 0 0 1000px var(--aero-field-bg) inset;
    -webkit-text-fill-color: var(--aero-text);
}

.aero-input__error {
    color: var(--aero-error);
    font-size: var(--aero-font-size-small);
}
</style>
