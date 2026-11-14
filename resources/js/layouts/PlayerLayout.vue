<script setup lang="ts">
// The persistent layout of every signed-in page (spotify-web-player, design D1 and D10): the page, and the player
// bar fixed at the bottom. Inertia keeps it mounted across visits, so the Spotify device survives navigation.
import AeroPlayerBar from '@/components/aero/AeroPlayerBar.vue';
import { useSpotifyPlayer } from '@/composables/useSpotifyPlayer';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const page = usePage<SharedData>();
const player = useSpotifyPlayer();

const bar = ref<HTMLElement | null>(null);
const barHeight = ref(0);
let observer: ResizeObserver | null = null;

const connected = computed(() => page.props.spotify?.connected === true);
const ready = computed(() => player.state.deviceId !== null);
const track = computed(() => player.state.track);

const notice = computed(() => {
    if (!connected.value) {
        return 'Connect Spotify to play your music.';
    }

    return player.state.autoplayBlocked ? 'Press play to start listening' : null;
});

// Start the device when Spotify is connected, stop it when the connection goes away (design D10).
watch(
    connected,
    (isConnected) => {
        if (isConnected) {
            player.start(page.props.spotify.sdkUrl ?? '', page.props.name);
        } else {
            player.stop();
        }
    },
    { immediate: true },
);

onMounted(() => {
    if (!bar.value) {
        return;
    }

    // The page and the kit's fixed sidebar end above the bar (design D1).
    observer = new ResizeObserver(([entry]) => {
        barHeight.value = Math.ceil(entry.borderBoxSize?.[0]?.blockSize ?? entry.target.getBoundingClientRect().height);
        document.documentElement.style.setProperty('--app-player-height', `${barHeight.value}px`);
    });
    observer.observe(bar.value);
});

// Leaving the signed-in pages, such as after logging out, disconnects the device.
onBeforeUnmount(() => {
    observer?.disconnect();
    document.documentElement.style.removeProperty('--app-player-height');
    player.stop();
});
</script>

<template>
    <div class="app-player-layout" :style="{ paddingBottom: `${barHeight}px` }">
        <slot />
    </div>

    <div id="app-player" ref="bar" class="app-player">
        <AeroPlayerBar
            :title="track?.title ?? ''"
            :artist="track?.artists.join(', ') ?? ''"
            :cover-src="track?.image ?? null"
            :playing="!player.state.paused"
            :position="Math.floor(player.positionMs.value / 1000)"
            :duration="Math.floor((track?.durationMs ?? 0) / 1000)"
            :volume="player.state.volume"
            :previous-disabled="!ready"
            :play-disabled="!ready"
            :next-disabled="!ready || !player.state.hasNext"
            :seek-disabled="!ready || !track"
            @update:position="(seconds: number) => player.seek(seconds * 1000)"
            @update:volume="player.setVolume"
            @toggle-play="player.togglePlay"
            @previous="player.previous"
            @next="player.next"
        >
            <template v-if="notice" #notice>
                <span data-part="player-notice">{{ notice }}</span>
            </template>
            <template v-if="!connected" #notice-actions>
                <Link href="/settings/spotify" class="app-player__link">Spotify settings</Link>
            </template>
        </AeroPlayerBar>
    </div>
</template>

<style scoped>
.app-player {
    position: fixed;
    inset-inline: 0;
    bottom: 0;
    z-index: 40;
    padding: var(--aero-space-2);
}

.app-player__link {
    display: inline-flex;
    align-items: center;
    min-height: var(--aero-touch-min);
    padding: 0 var(--aero-space-2);
    color: var(--aero-text);
    font-family: var(--aero-font-family);
    text-decoration: underline;
}

/* The kit's fixed sidebar (dashboard) ends above the bar, so its user menu stays clickable, until T-25. */
:global(div.fixed:has(> [data-sidebar='sidebar'])) {
    bottom: var(--app-player-height, 0px);
    height: auto;
}
</style>
