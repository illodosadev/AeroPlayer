<script setup lang="ts">
// Spotify settings (spotify-connection, design D8, D9 and D11): the connection state, the page message and the
// connect and disconnect buttons, built from the Aero components only.
import AeroBackground from '@/components/aero/AeroBackground.vue';
import AeroButton from '@/components/aero/AeroButton.vue';
import AeroGlassPanel from '@/components/aero/AeroGlassPanel.vue';
import type { SpotifyStatus } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    configured: boolean;
    status: SpotifyStatus;
    accountName: string | null;
    reconnectBefore: string | null;
    missingScopes: boolean;
    message: { tone: 'success' | 'info' | 'error'; text: string; detail?: string } | null;
}>();

const disconnecting = ref(false);

const connected = computed(() => props.status === 'connected');
const showConnect = computed(() => props.configured && (!connected.value || props.missingScopes));
const showDisconnect = computed(() => props.status !== 'not_connected');

function disconnect(): void {
    router.delete('/spotify/connection', {
        preserveScroll: true,
        onStart: () => (disconnecting.value = true),
        onFinish: () => (disconnecting.value = false),
    });
}
</script>

<template>
    <Head title="Spotify settings" />

    <AeroBackground>
        <main id="spotify-settings" class="mx-auto flex min-h-svh w-full max-w-md flex-col justify-center gap-3 p-4 sm:p-6">
            <Link href="/dashboard" class="spotify-settings__back self-start">Back</Link>

            <AeroGlassPanel>
                <h1 id="spotify-settings-title" class="mb-4 text-xl font-semibold">Spotify</h1>

                <div role="status" aria-live="polite" class="spotify-settings__message" :data-tone="message?.tone">
                    <template v-if="message">
                        <p data-part="message">{{ message.text }}</p>
                        <p v-if="message.detail" data-part="message-detail">{{ message.detail }}</p>
                    </template>
                </div>

                <div data-part="state" class="flex flex-col gap-1">
                    <template v-if="!configured">
                        <p>Spotify is not configured.</p>
                        <p class="spotify-settings__muted">Set the Spotify variables in the server's .env file.</p>
                    </template>

                    <template v-if="status === 'not_connected'">
                        <p v-if="configured">Spotify is not connected.</p>
                    </template>

                    <template v-else-if="status === 'reconnect_required'">
                        <p>Your Spotify access expired or was revoked. Please reconnect Spotify.</p>
                    </template>

                    <template v-else>
                        <p class="font-semibold">Spotify connected</p>
                        <p data-part="account">{{ accountName }}</p>
                        <p v-if="reconnectBefore" data-part="reconnect-before" class="spotify-settings__muted">
                            Reconnect before {{ reconnectBefore }}
                        </p>
                        <p v-if="missingScopes">Reconnect Spotify to grant the new permissions.</p>
                    </template>
                </div>

                <div class="mt-5 flex flex-wrap gap-3">
                    <!-- A full page navigation, not an Inertia visit: the answer is a redirect to Spotify (design D11). -->
                    <form v-if="showConnect" method="get" action="/spotify/connect">
                        <AeroButton id="spotify-connect" type="submit">Connect Spotify</AeroButton>
                    </form>

                    <AeroButton v-if="showDisconnect" id="spotify-disconnect" :disabled="disconnecting" @click="disconnect">
                        Disconnect Spotify
                    </AeroButton>
                </div>
            </AeroGlassPanel>
        </main>
    </AeroBackground>
</template>

<style scoped>
.spotify-settings__back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: var(--aero-touch-min);
    min-height: var(--aero-touch-min);
    padding: 0 var(--aero-space-2);
    color: var(--aero-text);
    font-family: var(--aero-font-family);
    text-decoration: underline;
}

.spotify-settings__message:not(:empty) {
    margin-bottom: var(--aero-space-4);
    padding: var(--aero-space-2) var(--aero-space-3);
    border: 1px solid var(--aero-panel-border);
    border-radius: var(--aero-radius-md);
    color: var(--aero-text);
}

.spotify-settings__message[data-tone='error'] {
    color: var(--aero-error);
}

.spotify-settings__muted {
    color: var(--aero-text-muted);
    font-size: var(--aero-font-size-small);
}
</style>
