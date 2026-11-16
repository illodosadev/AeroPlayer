<script setup lang="ts">
// The "Queue" panel above the player bar (playback-queue, design D9): the current song under "Now playing" and the
// pending ones under "Next up". Pending songs move with "Move up", "Move down" or by dragging, and any song can be
// removed. Every change waits for the server; a row whose edit is pending is aria-disabled but keeps the focus.
import AeroButton from '@/components/aero/AeroButton.vue';
import AeroCover from '@/components/aero/AeroCover.vue';
import AeroGlassPanel from '@/components/aero/AeroGlassPanel.vue';
import AeroIcon from '@/components/aero/AeroIcon.vue';
import { useQueue, type QueueItem } from '@/composables/useQueue';
import { formatTime } from '@/lib/formatTime';
import { sourceLabel } from '@/lib/sources';
import { computed, nextTick, onMounted, ref } from 'vue';

type Action = 'move-up' | 'move-down' | 'remove';

const queue = useQueue();

const root = ref<HTMLElement | null>(null);
const heading = ref<HTMLElement | null>(null);
const dragged = ref<number | null>(null);

const empty = computed(() => queue.current.value === null);

onMounted(() => heading.value?.focus());

function pending(item: QueueItem): boolean {
    return queue.isItemPending(item.item_id);
}

/**
 * Put the focus on a row's button after the list changed, or on the heading when there is none (design D9).
 */
async function focusAction(itemId: number | null, action: Action): Promise<void> {
    await nextTick();

    const row = itemId === null ? null : root.value?.querySelector<HTMLElement>(`[data-item-id="${itemId}"]`);

    if (!row) {
        heading.value?.focus();

        return;
    }

    const button = row.querySelector<HTMLButtonElement>(`[data-action="${action}"]`);
    const other = row.querySelector<HTMLButtonElement>(`[data-action="${action === 'move-up' ? 'move-down' : 'move-up'}"]`);

    if (button && !button.disabled) {
        button.focus();
    } else if (action !== 'remove' && other && !other.disabled) {
        other.focus();
    } else {
        row.querySelector<HTMLButtonElement>('[data-action="remove"]')?.focus();
    }
}

async function move(item: QueueItem, position: number, action: Action | null): Promise<void> {
    if (pending(item)) {
        return;
    }

    await queue.move(item.item_id, position);

    if (action) {
        await focusAction(item.item_id, action);
    }
}

async function remove(item: QueueItem): Promise<void> {
    if (pending(item)) {
        return;
    }

    const rows = [queue.current.value, ...queue.upNext.value].filter((row): row is QueueItem => row !== null);
    const index = rows.findIndex((row) => row.item_id === item.item_id);
    const neighbour = rows[index + 1] ?? rows[index - 1] ?? null;

    await queue.remove(item.item_id);
    await focusAction(neighbour?.item_id ?? null, 'remove');
}

function onDragStart(event: DragEvent, item: QueueItem): void {
    dragged.value = item.item_id;
    event.dataTransfer?.setData('text/plain', String(item.item_id));

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

function onDrop(target: number): void {
    const item = queue.upNext.value.find((row) => row.item_id === dragged.value);
    dragged.value = null;

    if (item && queue.upNext.value.indexOf(item) !== target) {
        void move(item, target, null);
    }
}

defineExpose({ focusHeading: () => heading.value?.focus() });
</script>

<template>
    <div id="queue-panel" class="queue-panel" data-part="queue-panel">
        <AeroGlassPanel aria-labelledby="queue-panel-title">
            <div ref="root" class="queue-panel__body">
                <h2 id="queue-panel-title" ref="heading" class="queue-panel__title" tabindex="-1">Queue</h2>

                <p v-if="empty" class="queue-panel__empty" data-part="queue-empty">Your queue is empty.</p>

                <template v-else>
                    <h3 class="queue-panel__heading">Now playing</h3>
                    <ul class="queue-panel__list" data-section="now-playing">
                        <li
                            v-if="queue.current.value"
                            :key="queue.current.value.item_id"
                            class="queue-row"
                            data-part="queue-row"
                            :data-item-id="queue.current.value.item_id"
                            :aria-labelledby="`queue-row-${queue.current.value.item_id}`"
                        >
                            <AeroCover :src="queue.current.value.image" :alt="`${queue.current.value.title} cover`" :size="40" />
                            <span class="queue-row__text">
                                <span :id="`queue-row-${queue.current.value.item_id}`" class="queue-row__title" data-part="title">
                                    {{ queue.current.value.title }}
                                </span>
                                <span class="queue-row__detail" data-part="artists">{{ queue.current.value.artists.join(', ') }}</span>
                            </span>
                            <span class="queue-row__meta">
                                <span data-part="duration">{{ formatTime(queue.current.value.duration_ms / 1000) }}</span>
                                <span class="queue-row__source" data-part="source">{{ sourceLabel(queue.current.value.source) }}</span>
                            </span>
                            <span class="queue-row__actions">
                                <AeroButton
                                    shape="orb"
                                    data-action="remove"
                                    aria-label="Remove"
                                    :aria-disabled="pending(queue.current.value) ? 'true' : undefined"
                                    @click="remove(queue.current.value)"
                                >
                                    <AeroIcon name="remove" />
                                </AeroButton>
                            </span>
                        </li>
                    </ul>

                    <template v-if="queue.upNext.value.length > 0">
                        <h3 class="queue-panel__heading">Next up</h3>
                        <ul class="queue-panel__list" data-section="next-up">
                            <li
                                v-for="(item, index) in queue.upNext.value"
                                :key="item.item_id"
                                class="queue-row"
                                data-part="queue-row"
                                :data-item-id="item.item_id"
                                :aria-labelledby="`queue-row-${item.item_id}`"
                                draggable="true"
                                @dragstart="onDragStart($event, item)"
                                @dragover.prevent
                                @drop.prevent="onDrop(index)"
                            >
                                <AeroCover :src="item.image" :alt="`${item.title} cover`" :size="40" />
                                <span class="queue-row__text">
                                    <span :id="`queue-row-${item.item_id}`" class="queue-row__title" data-part="title">{{ item.title }}</span>
                                    <span class="queue-row__detail" data-part="artists">{{ item.artists.join(', ') }}</span>
                                </span>
                                <span class="queue-row__meta">
                                    <span data-part="duration">{{ formatTime(item.duration_ms / 1000) }}</span>
                                    <span class="queue-row__source" data-part="source">{{ sourceLabel(item.source) }}</span>
                                </span>
                                <span class="queue-row__actions">
                                    <AeroButton
                                        shape="orb"
                                        data-action="move-up"
                                        aria-label="Move up"
                                        :disabled="index === 0"
                                        :aria-disabled="pending(item) ? 'true' : undefined"
                                        @click="move(item, index - 1, 'move-up')"
                                    >
                                        <AeroIcon name="move-up" />
                                    </AeroButton>
                                    <AeroButton
                                        shape="orb"
                                        data-action="move-down"
                                        aria-label="Move down"
                                        :disabled="index === queue.upNext.value.length - 1"
                                        :aria-disabled="pending(item) ? 'true' : undefined"
                                        @click="move(item, index + 1, 'move-down')"
                                    >
                                        <AeroIcon name="move-down" />
                                    </AeroButton>
                                    <AeroButton
                                        shape="orb"
                                        data-action="remove"
                                        aria-label="Remove"
                                        :aria-disabled="pending(item) ? 'true' : undefined"
                                        @click="remove(item)"
                                    >
                                        <AeroIcon name="remove" />
                                    </AeroButton>
                                </span>
                            </li>
                        </ul>
                    </template>
                </template>
            </div>
        </AeroGlassPanel>
    </div>
</template>

<style scoped>
.queue-panel {
    position: absolute;
    bottom: 100%;
    inset-inline-end: var(--aero-space-2);
    width: min(28rem, calc(100vw - 2 * var(--aero-space-2)));
}

/* The panel's own padding goes to the scrolling body, so the scrollbar sits at the edge. */
.queue-panel > [data-aero='glass-panel'] {
    padding: 0;
}

.queue-panel__body {
    max-height: 60vh;
    overflow-y: auto;
    padding: var(--aero-space-3);
    color: var(--aero-text);
    font-family: var(--aero-font-family);
}

.queue-panel__title {
    margin: 0 0 var(--aero-space-2);
    font-size: var(--aero-font-size-body);
    font-weight: 700;
}

.queue-panel__heading {
    margin: var(--aero-space-2) 0 var(--aero-space-1);
    color: var(--aero-text-muted);
    font-size: var(--aero-font-size-small);
    font-weight: 600;
}

.queue-panel__empty {
    margin: 0;
}

.queue-panel__list {
    margin: 0;
    padding: 0;
    list-style: none;
}

.queue-row {
    display: flex;
    align-items: center;
    gap: var(--aero-space-2);
    min-height: var(--aero-touch-min);
    padding: var(--aero-space-1) 0;
    font-size: var(--aero-font-size-body);
}

.queue-row[draggable='true'] {
    cursor: grab;
}

.queue-row__text {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
}

.queue-row__title,
.queue-row__detail {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.queue-row__title {
    font-weight: 600;
}

.queue-row__detail {
    color: var(--aero-text-muted);
    font-size: var(--aero-font-size-small);
}

.queue-row__meta {
    display: flex;
    flex: none;
    flex-direction: column;
    align-items: flex-end;
    gap: var(--aero-space-1);
    font-size: var(--aero-font-size-small);
}

.queue-row__source {
    padding: 0 var(--aero-space-2);
    border: 1px solid var(--aero-panel-border);
    border-radius: var(--aero-radius-pill);
}

.queue-row__actions {
    display: flex;
    flex: none;
    gap: var(--aero-space-1);
}
</style>
