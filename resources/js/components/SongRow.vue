<script setup lang="ts">
// One song as a pressable row (spotify-library-tracks, design D5), shared by the search, playlist and Liked Songs pages.
// A song that cannot play is a disabled button, dimmed, with the reason in place of its duration.
import AeroCover from '@/components/aero/AeroCover.vue';
import { formatTime } from '@/lib/formatTime';
import { sourceLabel } from '@/lib/sources';
import { computed } from 'vue';

export interface SongRowSong {
    source: string;
    id: string;
    uri: string;
    title: string;
    artists: string[];
    album: string | null;
    duration_ms: number;
    image: string | null;
    playable: boolean;
    local?: boolean;
    position?: number;
}

const props = defineProps<{ song: SongRowSong }>();

defineEmits<{ play: [] }>();

const reason = computed(() => (props.song.local ? "Local file, can't play here" : 'Not available'));
const label = computed(() => sourceLabel(props.song.source));
</script>

<template>
    <button
        type="button"
        class="song-row"
        data-part="song-row"
        :data-id="song.id"
        :data-position="song.position"
        :data-playable="song.playable ? 'true' : 'false'"
        :data-local="song.local ? 'true' : undefined"
        :disabled="!song.playable"
        @click="$emit('play')"
    >
        <AeroCover :src="song.image" :alt="`${song.title} cover`" :size="48" />
        <span class="song-row__text">
            <span class="song-row__title" data-part="title">{{ song.title }}</span>
            <span class="song-row__detail">
                <span data-part="artists">{{ song.artists.join(', ') }}</span>
                <template v-if="song.album">
                    · <span data-part="album">{{ song.album }}</span>
                </template>
            </span>
        </span>
        <span class="song-row__meta">
            <span v-if="song.playable" data-part="duration">{{ formatTime(song.duration_ms / 1000) }}</span>
            <span v-else data-part="not-available">{{ reason }}</span>
            <span class="song-row__source" data-part="source">{{ label }}</span>
        </span>
    </button>
</template>

<style scoped>
.song-row {
    display: flex;
    align-items: center;
    gap: var(--aero-space-3);
    width: 100%;
    min-height: var(--aero-touch-min);
    padding: var(--aero-space-1) var(--aero-space-2);
    border: 1px solid transparent;
    border-radius: var(--aero-radius-sm);
    background: none;
    color: var(--aero-text);
    font-family: var(--aero-font-family);
    font-size: var(--aero-font-size-body);
    text-align: start;
    cursor: pointer;
}

.song-row:not(:disabled):hover {
    border-color: var(--aero-panel-border);
    background: var(--aero-field-bg);
}

/* A song that cannot play: dimmed cover, muted text that still keeps its contrast. */
.song-row:disabled {
    color: var(--aero-text-muted);
    cursor: default;
}

.song-row:disabled :deep([data-aero='cover']) {
    opacity: 0.5;
}

.song-row__text {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
}

.song-row__title,
.song-row__detail {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.song-row__title {
    font-weight: 600;
}

.song-row__detail {
    color: var(--aero-text-muted);
    font-size: var(--aero-font-size-small);
}

.song-row__meta {
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    align-items: flex-end;
    gap: var(--aero-space-1);
    font-size: var(--aero-font-size-small);
}

.song-row__source {
    padding: 0 var(--aero-space-2);
    border: 1px solid var(--aero-panel-border);
    border-radius: var(--aero-radius-pill);
    color: var(--aero-text);
}
</style>
