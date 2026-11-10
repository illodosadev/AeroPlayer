<script setup lang="ts">
// Glass panel: a translucent pane with a top reflection. Without backdrop blur it falls back to an almost opaque pane
// (design D3, D4). The forced fallback class on <html> is handled in resources/css/aero/base.css.
</script>

<template>
    <section data-aero="glass-panel" class="aero-glass-panel">
        <slot />
    </section>
</template>

<style scoped>
.aero-glass-panel {
    position: relative;
    padding: var(--aero-space-6);
    border: 1px solid var(--aero-panel-border);
    border-radius: var(--aero-radius-md);
    box-shadow: var(--aero-panel-shadow);
    background: var(--aero-panel-bg-fallback);
    color: var(--aero-text);
    font-family: var(--aero-font-family);
}

.aero-glass-panel::before {
    content: '';
    position: absolute;
    inset: 0 0 50% 0;
    border-radius: inherit;
    background: linear-gradient(rgba(255, 255, 255, 0.55), rgba(255, 255, 255, 0));
    pointer-events: none;
}

@supports ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
    .aero-glass-panel {
        background: var(--aero-panel-bg);
        backdrop-filter: blur(var(--aero-blur));
        -webkit-backdrop-filter: blur(var(--aero-blur));
    }
}
</style>
