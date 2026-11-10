<script setup lang="ts">
// Aero layout for the guest pages: the sky background with a centred glass panel (design D10).
import AeroBackground from '@/components/aero/AeroBackground.vue';
import AeroGlassPanel from '@/components/aero/AeroGlassPanel.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <AeroBackground>
        <div class="flex min-h-svh items-center justify-center p-4 sm:p-6">
            <div class="w-full max-w-sm">
                <AeroGlassPanel>
                    <div class="mb-6 flex flex-col items-center gap-3 text-center">
                        <AppLogoIcon class="size-10 fill-current text-[var(--aero-gel-base)]" />
                        <h1 id="auth-title" class="text-xl font-semibold">{{ title }}</h1>
                        <p class="text-sm text-[var(--aero-text-muted)]">{{ description }}</p>
                    </div>
                    <slot />
                </AeroGlassPanel>
            </div>
        </div>
    </AeroBackground>
</template>
