<script setup lang="ts">
// The settings link bar shared by the Spotify and YouTube Music settings pages (youtube-music-connection, design
// D9). Plain links, since each page has its own URL; the current one carries aria-current="page".
import { Link } from '@inertiajs/vue3';

defineProps<{ current: 'spotify' | 'youtube-music' }>();

const links = [
    { key: 'spotify', label: 'Spotify', href: '/settings/spotify' },
    { key: 'youtube-music', label: 'YouTube Music', href: '/settings/youtube-music' },
] as const;
</script>

<template>
    <nav aria-label="Settings" data-part="settings-nav" class="settings-nav flex flex-wrap gap-2">
        <Link
            v-for="link in links"
            :key="link.key"
            :href="link.href"
            :aria-current="current === link.key ? 'page' : undefined"
            class="settings-nav__link"
        >
            {{ link.label }}
        </Link>
    </nav>
</template>

<style scoped>
.settings-nav__link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: var(--aero-touch-min);
    min-height: var(--aero-touch-min);
    padding: 0 var(--aero-space-4);
    border: 1px solid var(--aero-panel-border);
    border-radius: var(--aero-radius-pill);
    background: var(--aero-panel-bg-fallback);
    color: var(--aero-text);
    font-family: var(--aero-font-family);
    font-size: var(--aero-font-size-body);
    text-decoration: none;
}

.settings-nav__link:hover {
    text-decoration: underline;
}

.settings-nav__link[aria-current='page'] {
    font-weight: 700;
    border-color: var(--aero-gel-base);
    box-shadow: inset 0 0 0 1px var(--aero-gel-base);
}
</style>
