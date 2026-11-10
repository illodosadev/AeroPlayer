<script setup lang="ts">
// Full-screen sky layer. The image sits above the gradient in --aero-background-image, so the gradient shows first and
// stays visible when the image fails or loads late (design D3). The bubbles are decorative and hidden from assistive tech.
</script>

<template>
    <div data-aero="background" class="aero-background">
        <div class="aero-background__bubbles" aria-hidden="true">
            <span class="aero-background__bubble aero-background__bubble--1" />
            <span class="aero-background__bubble aero-background__bubble--2" />
            <span class="aero-background__bubble aero-background__bubble--3" />
        </div>
        <div class="aero-background__content">
            <slot />
        </div>
    </div>
</template>

<style scoped>
.aero-background {
    position: relative;
    min-height: 100vh;
    min-height: 100svh;
    overflow: hidden;
    background-color: var(--aero-sky-horizon);
    background-image: var(--aero-background-image), var(--aero-background-fallback);
    background-size: cover;
    background-position: center bottom;
    background-repeat: no-repeat;
}

.aero-background__content {
    position: relative;
    z-index: 1;
}

.aero-background__bubbles {
    position: absolute;
    inset: 0;
    z-index: 0;
    pointer-events: none;
}

.aero-background__bubble {
    position: absolute;
    bottom: -120px;
    border-radius: 50%;
    background: radial-gradient(circle at 35% 30%, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.15) 60%, rgba(255, 255, 255, 0.05));
    box-shadow: inset 0 -6px 12px rgba(255, 255, 255, 0.35);
    animation: aero-bubble-rise 22s linear infinite;
}

.aero-background__bubble--1 { left: 12%; width: 56px; height: 56px; }
.aero-background__bubble--2 { left: 70%; width: 90px; height: 90px; animation-duration: 30s; animation-delay: -8s; }
.aero-background__bubble--3 { left: 42%; width: 34px; height: 34px; animation-duration: 18s; animation-delay: -4s; }

@keyframes aero-bubble-rise {
    from { transform: translateY(0); opacity: 0; }
    10% { opacity: 0.9; }
    to { transform: translateY(-115vh); opacity: 0; }
}
</style>
