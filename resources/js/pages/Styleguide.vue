<script setup lang="ts">
import AeroBackground from '@/components/aero/AeroBackground.vue';
import AeroButton from '@/components/aero/AeroButton.vue';
import AeroCheckbox from '@/components/aero/AeroCheckbox.vue';
import AeroInput from '@/components/aero/AeroInput.vue';
import AeroGlassPanel from '@/components/aero/AeroGlassPanel.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const clicks = ref(0);
const disabledClicks = ref(0);
const email = ref('');
const cbUnchecked = ref(false);
const cbChecked = ref(true);
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
                    <AeroInput id="styleguide-email" v-model="email" label="Email" type="email" autocomplete="email" placeholder="email@example.com" />
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
        </main>
    </AeroBackground>
</template>
