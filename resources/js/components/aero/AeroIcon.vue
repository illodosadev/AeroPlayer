<script setup lang="ts">
// Hand-made icons from resources/aero/icons, inlined so they take the text colour through currentColor (design D4).
// v-html only ever receives these repository files, loaded at build time, never user data.
import { computed } from 'vue';

export type AeroIconName = 'previous' | 'play' | 'pause' | 'next' | 'volume' | 'note';

const files = import.meta.glob<string>('../../../aero/icons/*.svg', { query: '?raw', import: 'default', eager: true });

const props = defineProps<{
    name: AeroIconName;
}>();

const svg = computed(() => files[`../../../aero/icons/${props.name}.svg`] ?? '');
</script>

<template>
    <span data-aero="icon" :data-icon="name" class="aero-icon" aria-hidden="true" v-html="svg" />
</template>

<style scoped>
.aero-icon {
    display: inline-flex;
    width: 1.5em;
    height: 1.5em;
    flex: none;
}

.aero-icon :deep(svg) {
    width: 100%;
    height: 100%;
}
</style>
