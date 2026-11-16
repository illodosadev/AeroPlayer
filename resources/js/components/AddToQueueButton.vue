<script setup lang="ts">
// "Add to queue" beside a song row (playback-queue, design D7). Disabled for a song that cannot play. While its add is
// pending it is aria-disabled and ignores presses, but keeps the keyboard focus: a focused button that becomes disabled
// would drop the focus to the page.
import AeroButton from '@/components/aero/AeroButton.vue';
import AeroIcon from '@/components/aero/AeroIcon.vue';
import { useQueue, type QueueSong } from '@/composables/useQueue';
import { computed } from 'vue';

const props = defineProps<{ song: QueueSong }>();

const queue = useQueue();

const pending = computed(() => queue.isAddPending(props.song.uri));

function add(): void {
    if (!pending.value) {
        void queue.add(props.song);
    }
}
</script>

<template>
    <AeroButton
        shape="orb"
        class="add-to-queue"
        data-part="add-to-queue"
        aria-label="Add to queue"
        title="Add to queue"
        :disabled="!song.playable"
        :aria-disabled="pending ? 'true' : undefined"
        @click="add"
    >
        <AeroIcon name="queue-add" />
    </AeroButton>
</template>

<style scoped>
.add-to-queue {
    flex: none;
}
</style>
