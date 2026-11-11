<script setup lang="ts">
// Visual-only player bar (aero-player-controls, design D3): an Aero glass strip with the cover, the song, the
// transport orbs (a large "Play"/"Pause" between "Previous" and "Next", as in WMP 11), "Seek" and "Volume".
// The parent owns all state (the styleguide now, T-06 later): the bar only emits events and never plays sound.
// Slots: "notice" (the message, inside an always-rendered polite status region), "notice-actions" (a button and a
// link, outside that region) and "extra" (more AeroButtons from other features).
import { formatTime } from '@/lib/formatTime';
import { computed, useId, useSlots } from 'vue';
import AeroButton from './AeroButton.vue';
import AeroCover from './AeroCover.vue';
import AeroGlassPanel from './AeroGlassPanel.vue';
import AeroIcon from './AeroIcon.vue';
import AeroSlider from './AeroSlider.vue';

const props = withDefaults(
    defineProps<{
        title?: string;
        artist?: string;
        coverSrc?: string | null;
        playing?: boolean;
        position?: number;
        duration?: number;
        volume?: number;
        previousDisabled?: boolean;
        playDisabled?: boolean;
        nextDisabled?: boolean;
        seekDisabled?: boolean;
    }>(),
    {
        title: '',
        artist: '',
        coverSrc: null,
        playing: false,
        position: 0,
        duration: 0,
        volume: 100,
        previousDisabled: false,
        playDisabled: false,
        nextDisabled: false,
        seekDisabled: false,
    },
);

const emit = defineEmits<{
    'update:position': [seconds: number];
    'update:volume': [volume: number];
    'toggle-play': [];
    previous: [];
    next: [];
}>();

const slots = useSlots();
const uid = useId();

const elapsed = computed(() => formatTime(props.position));
const total = computed(() => formatTime(props.duration));
const coverAlt = computed(() => (props.title ? `${props.title} cover` : 'Cover'));
const hasNotice = computed(() => Boolean(slots.notice || slots['notice-actions']));
</script>

<template>
    <AeroGlassPanel class="aero-player-bar-panel">
        <div data-aero="player-bar" class="aero-player-bar flex flex-wrap items-center gap-x-3 gap-y-2">
            <div class="flex min-w-0 flex-[1_1_10rem] items-center gap-3">
                <AeroCover class="aero-player-bar__cover" :src="coverSrc" :alt="coverAlt" />
                <div class="aero-player-bar__meta min-w-0 flex-1">
                    <p data-part="title" class="aero-player-bar__title" :title="title || undefined">{{ title }}</p>
                    <p data-part="artist" class="aero-player-bar__artist" :title="artist || undefined">{{ artist }}</p>
                </div>
            </div>

            <div class="flex flex-none items-center gap-1">
                <AeroButton :id="`${uid}-previous`" shape="orb" aria-label="Previous" :disabled="previousDisabled" @click="emit('previous')">
                    <AeroIcon name="previous" />
                </AeroButton>
                <AeroButton
                    :id="`${uid}-play`"
                    shape="orb"
                    size="lg"
                    :aria-label="playing ? 'Pause' : 'Play'"
                    :disabled="playDisabled"
                    @click="emit('toggle-play')"
                >
                    <AeroIcon :name="playing ? 'pause' : 'play'" />
                </AeroButton>
                <AeroButton :id="`${uid}-next`" shape="orb" aria-label="Next" :disabled="nextDisabled" @click="emit('next')">
                    <AeroIcon name="next" />
                </AeroButton>
            </div>

            <div class="flex basis-full items-center gap-2 sm:flex-[3_1_7rem]">
                <span data-time="elapsed" class="aero-player-bar__time">{{ elapsed }}</span>
                <AeroSlider
                    :id="`${uid}-seek`"
                    class="min-w-0 flex-1"
                    label="Seek"
                    :model-value="position"
                    :min="0"
                    :max="duration"
                    :step="1"
                    :disabled="seekDisabled"
                    :value-text="`${elapsed} of ${total}`"
                    @update:model-value="emit('update:position', $event)"
                />
                <span data-time="total" class="aero-player-bar__time">{{ total }}</span>
            </div>

            <div class="flex flex-[1_1_5rem] items-center gap-1">
                <AeroIcon class="aero-player-bar__volume-icon" name="volume" />
                <AeroSlider
                    :id="`${uid}-volume`"
                    class="min-w-0 flex-1"
                    label="Volume"
                    :model-value="volume"
                    :min="0"
                    :max="100"
                    :step="1"
                    @update:model-value="emit('update:volume', $event)"
                />
            </div>

            <div v-if="slots.extra" class="flex flex-none items-center gap-2">
                <slot name="extra" />
            </div>

            <div
                class="aero-player-bar__notice flex basis-full flex-wrap items-center gap-x-3 gap-y-1"
                :class="{ 'aero-player-bar__notice--active': hasNotice }"
            >
                <div role="status" aria-live="polite" class="aero-player-bar__message">
                    <slot name="notice" />
                </div>
                <div v-if="slots['notice-actions']" class="aero-player-bar__actions flex flex-wrap items-center gap-2">
                    <slot name="notice-actions" />
                </div>
            </div>
        </div>
    </AeroGlassPanel>
</template>

<style scoped>
/* A thinner glass strip than a page panel. */
.aero-player-bar-panel {
    padding: var(--aero-space-3);
}

.aero-player-bar__cover {
    --_size: var(--aero-cover-size-bar);
}

.aero-player-bar__title,
.aero-player-bar__artist {
    overflow: hidden;
    /* An empty line keeps its height, so the bar does not move (design D3). */
    min-height: 1.3em;
    line-height: 1.3;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.aero-player-bar__title {
    color: var(--aero-text);
    font-size: var(--aero-font-size-body);
    font-weight: 700;
}

.aero-player-bar__artist {
    color: var(--aero-text-muted);
    font-size: var(--aero-font-size-small);
}

.aero-player-bar__time {
    flex: none;
    min-width: 3ch;
    color: var(--aero-text-muted);
    font-size: var(--aero-font-size-small);
    font-variant-numeric: tabular-nums;
}

.aero-player-bar__volume-icon {
    color: var(--aero-text-muted);
}

/* The notice row takes no room until there is a notice; the status region stays in the DOM to be announced. */
.aero-player-bar__notice:not(.aero-player-bar__notice--active) {
    /* Cancels the row gap (gap-y-2) above the empty notice row. */
    margin-top: -0.5rem;
}

.aero-player-bar__notice--active {
    padding-top: var(--aero-space-2);
    border-top: 1px solid var(--aero-panel-border);
}

.aero-player-bar__message {
    color: var(--aero-text);
    font-size: var(--aero-font-size-small);
}

.aero-player-bar__actions :slotted(a) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: var(--aero-touch-min);
    min-height: var(--aero-touch-min);
    color: var(--aero-text);
    font-size: var(--aero-font-size-small);
    text-decoration: underline;
}
</style>
