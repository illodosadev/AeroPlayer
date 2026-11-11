<script setup lang="ts">
// Song cover at a fixed size with a generic Aero fallback (design D2). States:
// - none / failed: no src, or the image failed: the fallback only, no <img>;
// - loading: the fallback with the <img> on top at opacity 0;
// - loaded: the <img> only.
// The fallback is drawn from theme tokens and the hand-made "note" icon, with no network request.
import { onMounted, ref, watch } from 'vue';
import AeroIcon from './AeroIcon.vue';

const props = defineProps<{
    src?: string | null;
    alt: string;
    size?: number;
}>();

type CoverState = 'none' | 'loading' | 'loaded' | 'failed';

const state = ref<CoverState>(props.src ? 'loading' : 'none');
const img = ref<HTMLImageElement | null>(null);

watch(
    () => props.src,
    (src) => {
        state.value = src ? 'loading' : 'none';
    },
);

// The image may finish before the listeners are attached (cached file): read its state once mounted.
onMounted(() => {
    if (img.value?.complete) {
        state.value = img.value.naturalWidth > 0 ? 'loaded' : 'failed';
    }
});
</script>

<template>
    <div data-aero="cover" class="aero-cover" :data-state="state" :style="size ? { '--_size': `${size}px` } : undefined">
        <div v-if="state !== 'loaded'" data-aero="cover-fallback" class="aero-cover__fallback" aria-hidden="true">
            <AeroIcon name="note" />
        </div>
        <img
            v-if="src && (state === 'loading' || state === 'loaded')"
            ref="img"
            class="aero-cover__image"
            :class="{ 'aero-cover__image--loading': state === 'loading' }"
            :src="src"
            :alt="alt"
            @load="state = 'loaded'"
            @error="state = 'failed'"
        />
    </div>
</template>

<style scoped>
.aero-cover {
    position: relative;
    flex: none;
    width: var(--_size, 64px);
    height: var(--_size, 64px);
    overflow: hidden;
    border: 1px solid var(--aero-panel-border);
    border-radius: var(--aero-radius-sm);
    box-shadow: 0 2px 5px var(--aero-gel-shadow);
}

/* Glossy reflection on the top half, as on framed album art. */
.aero-cover::after {
    content: '';
    position: absolute;
    inset: 0 0 50% 0;
    background: linear-gradient(rgba(255, 255, 255, 0.35), rgba(255, 255, 255, 0));
    pointer-events: none;
}

.aero-cover__fallback {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    background: linear-gradient(to bottom, var(--aero-sky-top) 0%, var(--aero-sky-horizon) 100%);
}

.aero-cover__fallback :deep([data-aero='icon']) {
    width: 50%;
    height: 50%;
    filter: drop-shadow(0 1px 1px var(--aero-gel-shadow));
}

.aero-cover__image {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.aero-cover__image--loading {
    opacity: 0;
}
</style>
