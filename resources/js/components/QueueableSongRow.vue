<script setup lang="ts">
// A song row with "Add to queue" beside it (playback-queue, design D7), for the search, playlist and Liked Songs pages.
// The row stays its own button with its own hooks, since a button cannot hold another.
import AddToQueueButton from '@/components/AddToQueueButton.vue';
import SongRow, { type SongRowSong } from '@/components/SongRow.vue';

defineProps<{ song: SongRowSong }>();

defineEmits<{ play: [] }>();
</script>

<template>
    <div class="queueable-song-row" data-part="queueable-song-row">
        <SongRow :song="song" @play="$emit('play')" />
        <AddToQueueButton :song="song" />
    </div>
</template>

<style scoped>
.queueable-song-row {
    display: flex;
    align-items: center;
    gap: var(--aero-space-2);
}

.queueable-song-row > :first-child {
    flex: 1;
    min-width: 0;
}
</style>
