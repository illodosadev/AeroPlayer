<script setup lang="ts">
import AeroBackground from '@/components/aero/AeroBackground.vue';
import AeroButton from '@/components/aero/AeroButton.vue';
import AeroCheckbox from '@/components/aero/AeroCheckbox.vue';
import AeroCover from '@/components/aero/AeroCover.vue';
import AeroGlassPanel from '@/components/aero/AeroGlassPanel.vue';
import AeroInput from '@/components/aero/AeroInput.vue';
import AeroPlayerBar from '@/components/aero/AeroPlayerBar.vue';
import AeroSlider from '@/components/aero/AeroSlider.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
// A real file served by the app, never inlined as a data URI, so the sample exercises a network load (design D7).
import sampleCover from '../../aero/samples/cover.svg?no-inline';

const clicks = ref(0);
const disabledClicks = ref(0);
const email = ref('');
const cbUnchecked = ref(false);
const cbChecked = ref(true);

// Sliders section (aero-player-controls, design D8).
const sliderVolume = ref(50);
const sliderSeek = ref(30);
const sliderDisabled = ref(40);

// Player bar section (design D8). Sample state only: pressing "Play" flips a flag and plays nothing.
const barTitle = ref('Song A');
const barArtist = ref('Artist A');
const barPlaying = ref(false);
const barPosition = ref(10);
const barDuration = ref(180);
const barVolume = ref(70);
const extraPressed = ref(false);

function toggleBarText(): void {
    const empty = barTitle.value === '' && barArtist.value === '';
    barTitle.value = empty ? 'Song A' : '';
    barArtist.value = empty ? 'Artist A' : '';
}

function longBarTitle(): void {
    barTitle.value = 'A very long song title that keeps going '.repeat(4).slice(0, 120);
}

function longBarSong(): void {
    barPosition.value = 0;
    barDuration.value = 3725;
}
</script>

<template>
    <Head title="Styleguide" />

    <AeroBackground>
        <main class="mx-auto flex max-w-3xl flex-col gap-6 p-6">
            <h1 id="styleguide-title" class="text-2xl font-semibold text-[var(--aero-text)]">Styleguide</h1>

            <AeroGlassPanel id="buttons">
                <h2 id="buttons-heading" class="mb-4 text-lg font-medium">Buttons</h2>

                <div class="grid grid-cols-[auto_1fr] items-center gap-x-4 gap-y-3">
                    <span class="text-sm">Rectangle</span>
                    <div class="flex flex-wrap gap-3">
                        <AeroButton data-sample="rect-normal">Normal</AeroButton>
                        <AeroButton data-sample="rect-hover" state="hover">Hover</AeroButton>
                        <AeroButton data-sample="rect-pressed" state="pressed">Pressed</AeroButton>
                        <AeroButton id="disabled-button" data-sample="rect-disabled" disabled @click="disabledClicks++">Disabled</AeroButton>
                    </div>

                    <span class="text-sm">Orb</span>
                    <div class="flex flex-wrap gap-3">
                        <AeroButton shape="orb" data-sample="orb-normal" aria-label="Orb normal">&#9679;</AeroButton>
                        <AeroButton shape="orb" data-sample="orb-hover" state="hover" aria-label="Orb hover">&#9679;</AeroButton>
                        <AeroButton shape="orb" data-sample="orb-pressed" state="pressed" aria-label="Orb pressed">&#9679;</AeroButton>
                        <AeroButton shape="orb" data-sample="orb-disabled" disabled aria-label="Orb disabled">&#9679;</AeroButton>
                    </div>

                    <span class="text-sm">Interactive</span>
                    <div class="flex flex-wrap items-center gap-3">
                        <AeroButton id="interactive-button" @click="clicks++">Press me</AeroButton>
                        <p class="text-sm">Clicks: {{ clicks }}</p>
                    </div>
                </div>

                <p class="mt-4 text-sm">Disabled clicks: {{ disabledClicks }}</p>
            </AeroGlassPanel>

            <AeroGlassPanel>
                <h2 id="glass-heading" class="mb-2 text-lg font-medium">Glass panel</h2>
                <p>This panel shows the translucent glass look over the sky.</p>
            </AeroGlassPanel>

            <AeroGlassPanel>
                <h2 id="inputs-heading" class="mb-4 text-lg font-medium">Inputs</h2>
                <div class="grid gap-4">
                    <AeroInput
                        id="styleguide-email"
                        v-model="email"
                        label="Email"
                        type="email"
                        autocomplete="email"
                        placeholder="email@example.com"
                    />
                    <AeroInput id="styleguide-error" label="Name" error="This field is required." />
                </div>
            </AeroGlassPanel>

            <AeroGlassPanel>
                <h2 id="checkbox-heading" class="mb-4 text-lg font-medium">Checkbox</h2>
                <div class="flex flex-col gap-2">
                    <AeroCheckbox id="cb-unchecked" v-model="cbUnchecked">Unchecked</AeroCheckbox>
                    <AeroCheckbox id="cb-checked" v-model="cbChecked">Checked</AeroCheckbox>
                </div>
            </AeroGlassPanel>

            <AeroGlassPanel id="sliders">
                <h2 id="sliders-heading" class="mb-4 text-lg font-medium">Sliders</h2>
                <div class="grid grid-cols-[auto_minmax(0,1fr)_3ch] items-center gap-x-4 gap-y-1">
                    <span class="text-sm">Volume</span>
                    <AeroSlider id="slider-volume" v-model="sliderVolume" label="Volume" />
                    <span id="slider-volume-value" class="text-sm tabular-nums">{{ sliderVolume }}</span>

                    <span class="text-sm">Seek</span>
                    <AeroSlider id="slider-seek" v-model="sliderSeek" label="Seek" />
                    <span id="slider-seek-value" class="text-sm tabular-nums">{{ sliderSeek }}</span>

                    <span class="text-sm">Disabled</span>
                    <AeroSlider id="slider-disabled" v-model="sliderDisabled" label="Disabled" disabled />
                    <span id="slider-disabled-value" class="text-sm tabular-nums">{{ sliderDisabled }}</span>
                </div>
            </AeroGlassPanel>

            <AeroGlassPanel id="cover">
                <h2 id="cover-heading" class="mb-4 text-lg font-medium">Cover</h2>
                <div class="flex flex-wrap gap-6">
                    <figure id="cover-with-image" class="flex flex-col items-center gap-2">
                        <AeroCover :src="sampleCover" alt="Sample cover" :size="96" />
                        <figcaption class="text-sm">With image</figcaption>
                    </figure>
                    <figure id="cover-no-image" class="flex flex-col items-center gap-2">
                        <AeroCover :src="null" alt="No cover" :size="96" />
                        <figcaption class="text-sm">No image</figcaption>
                    </figure>
                    <figure id="cover-broken" class="flex flex-col items-center gap-2">
                        <!-- A path the app answers with 404: a real failure, without request blocking (design D7). -->
                        <AeroCover src="/aero/missing-cover.png" alt="Broken cover" :size="96" />
                        <figcaption class="text-sm">Broken image</figcaption>
                    </figure>
                </div>
            </AeroGlassPanel>

            <AeroGlassPanel id="player-bar">
                <h2 id="player-bar-heading" class="mb-4 text-lg font-medium">Player bar</h2>
                <div class="flex flex-col gap-4">
                    <div id="player-bar-interactive">
                        <AeroPlayerBar
                            v-model:position="barPosition"
                            v-model:volume="barVolume"
                            :title="barTitle"
                            :artist="barArtist"
                            :cover-src="sampleCover"
                            :playing="barPlaying"
                            :duration="barDuration"
                            @toggle-play="barPlaying = !barPlaying"
                        />
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <AeroButton id="bar-toggle-text" @click="toggleBarText">Toggle text</AeroButton>
                        <AeroButton id="bar-long-title" @click="longBarTitle">Long title</AeroButton>
                        <AeroButton id="bar-long-song" @click="longBarSong">Long song</AeroButton>
                    </div>

                    <div id="player-bar-disabled">
                        <AeroPlayerBar
                            title="Song B"
                            artist="Artist B"
                            :position="20"
                            :duration="200"
                            :volume="40"
                            previous-disabled
                            play-disabled
                            next-disabled
                            seek-disabled
                        >
                            <template #extra>
                                <AeroButton id="bar-extra" :aria-pressed="extraPressed ? 'true' : 'false'" @click="extraPressed = !extraPressed"
                                    >Extra</AeroButton
                                >
                            </template>
                            <template #notice>Playing on Kitchen Speaker</template>
                            <template #notice-actions>
                                <AeroButton id="notice-play-here">Play here</AeroButton>
                                <a id="notice-reconnect" href="#" @click.prevent>Reconnect Spotify</a>
                            </template>
                        </AeroPlayerBar>
                    </div>
                </div>
            </AeroGlassPanel>
        </main>
    </AeroBackground>
</template>
