<script setup lang="ts">
// YouTube Music settings (youtube-music-connection, design D8 and D9): the connection state, Google's user code
// while an attempt waits, the page message and the connect and disconnect buttons, built from the Aero components.
import AeroBackground from '@/components/aero/AeroBackground.vue';
import AeroButton from '@/components/aero/AeroButton.vue';
import AeroGlassPanel from '@/components/aero/AeroGlassPanel.vue';
import SettingsNav from '@/components/settings/SettingsNav.vue';
import type { YouTubeMusicStatus } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    configured: boolean;
    status: YouTubeMusicStatus;
    channelName: string | null;
    waiting: { userCode: string; verificationUrl: string } | null;
    misconfigured: boolean;
    message: { tone: 'success' | 'info' | 'error'; text: string; detail?: string } | null;
}>();

/** How often the page asks for the status; the server paces the calls to Google (design D3). */
const POLL_MS = 2000;

const busy = ref(false);
let timer: ReturnType<typeof setTimeout> | null = null;
let stopped = false;

const connected = computed(() => props.status === 'connected');
// Also while waiting: pressing it again asks for a new code, which replaces the previous one (design D2).
const showConnect = computed(() => props.configured && !connected.value);
const showDisconnect = computed(() => props.status !== 'not_connected');

function connect(): void {
    router.post(
        '/youtube-music/connect',
        {},
        {
            preserveScroll: true,
            onStart: () => (busy.value = true),
            onFinish: () => (busy.value = false),
        },
    );
}

function disconnect(): void {
    router.delete('/youtube-music/connection', {
        preserveScroll: true,
        onStart: () => (busy.value = true),
        onFinish: () => (busy.value = false),
    });
}

function stopPolling(): void {
    stopped = true;

    if (timer !== null) {
        clearTimeout(timer);
        timer = null;
    }
}

async function poll(): Promise<void> {
    timer = null;
    let status: string | null = null;

    try {
        const response = await fetch('/youtube-music/connect/status', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (response.status === 401) {
            stopPolling();
            window.location.assign('/login');

            return;
        }

        status = response.ok ? ((await response.json()) as { status: string }).status : null;
    } catch {
        // A network blip: try again on the next tick.
    }

    if (stopped) {
        return;
    }

    if (status !== null && status !== 'pending') {
        // A final status: reload so the page shows the server's state and the flashed message.
        stopPolling();
        router.reload();

        return;
    }

    timer = setTimeout(poll, POLL_MS);
}

watch(
    () => props.waiting,
    (waiting) => {
        stopPolling();

        if (waiting !== null) {
            stopped = false;
            timer = setTimeout(poll, POLL_MS);
        }
    },
    { immediate: true },
);

onBeforeUnmount(stopPolling);
</script>

<template>
    <Head title="YouTube Music settings" />

    <AeroBackground>
        <main id="youtube-music-settings" class="mx-auto flex min-h-svh w-full max-w-md flex-col justify-center gap-3 p-4 sm:p-6">
            <Link href="/dashboard" class="youtube-music-settings__back self-start">Back</Link>

            <SettingsNav current="youtube-music" />

            <AeroGlassPanel>
                <h1 id="youtube-music-settings-title" class="mb-4 text-xl font-semibold">YouTube Music</h1>

                <div role="status" aria-live="polite" class="youtube-music-settings__message" :data-tone="message?.tone">
                    <template v-if="message">
                        <p data-part="message">{{ message.text }}</p>
                        <p v-if="message.detail" data-part="message-detail">{{ message.detail }}</p>
                    </template>
                </div>

                <div data-part="state" class="flex flex-col gap-1">
                    <template v-if="!configured">
                        <p>YouTube Music is not configured.</p>
                        <p class="youtube-music-settings__muted">Set the YouTube Music variables in the server's .env file.</p>
                    </template>

                    <template v-if="waiting">
                        <p>Open the address below and enter this code with your Google account:</p>
                        <p data-part="user-code" class="youtube-music-settings__code">{{ waiting.userCode }}</p>
                        <a
                            data-part="verification-url"
                            :href="waiting.verificationUrl"
                            target="_blank"
                            rel="noopener"
                            class="youtube-music-settings__link self-start"
                        >
                            {{ waiting.verificationUrl }}
                        </a>
                        <p data-part="waiting" class="youtube-music-settings__muted">Waiting for you to approve the code…</p>
                    </template>

                    <template v-else-if="status === 'not_connected'">
                        <p v-if="configured">YouTube Music is not connected.</p>
                    </template>

                    <template v-else-if="status === 'reconnect_required'">
                        <p>Your YouTube Music access expired or was revoked. Please reconnect YouTube Music.</p>
                    </template>

                    <template v-else>
                        <p class="font-semibold">YouTube Music connected</p>
                        <p data-part="channel">{{ channelName }}</p>
                        <!-- A renewal was refused for the client (youtube-music-credentials, design D5). -->
                        <p v-if="misconfigured" data-part="config-error" class="youtube-music-settings__error">
                            YouTube Music is not configured correctly.
                        </p>
                    </template>
                </div>

                <div class="mt-5 flex flex-wrap gap-3">
                    <AeroButton v-if="showConnect" id="youtube-music-connect" :disabled="busy" @click="connect"> Connect YouTube Music </AeroButton>

                    <AeroButton v-if="showDisconnect" id="youtube-music-disconnect" :disabled="busy" @click="disconnect">
                        Disconnect YouTube Music
                    </AeroButton>
                </div>
            </AeroGlassPanel>
        </main>
    </AeroBackground>
</template>

<style scoped>
.youtube-music-settings__back,
.youtube-music-settings__link {
    display: inline-flex;
    align-items: center;
    min-width: var(--aero-touch-min);
    min-height: var(--aero-touch-min);
    padding: 0 var(--aero-space-2);
    color: var(--aero-text);
    font-family: var(--aero-font-family);
    text-decoration: underline;
    overflow-wrap: anywhere;
}

.youtube-music-settings__back {
    justify-content: center;
}

.youtube-music-settings__message:not(:empty) {
    margin-bottom: var(--aero-space-4);
    padding: var(--aero-space-2) var(--aero-space-3);
    border: 1px solid var(--aero-panel-border);
    border-radius: var(--aero-radius-md);
    color: var(--aero-text);
}

.youtube-music-settings__message[data-tone='error'],
.youtube-music-settings__error {
    color: var(--aero-error);
}

.youtube-music-settings__code {
    font-family: var(--aero-font-family);
    font-size: var(--aero-font-size-title);
    font-weight: 700;
    letter-spacing: 0.1em;
    user-select: all;
}

.youtube-music-settings__muted {
    color: var(--aero-text-muted);
    font-size: var(--aero-font-size-small);
}
</style>
