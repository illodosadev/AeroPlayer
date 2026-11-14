<script setup lang="ts">
// The connection and failure states of the playlist and Liked Songs pages (spotify-library-tracks, design D5), as on
// the library page: always a message, never an error page.
import AeroButton from '@/components/aero/AeroButton.vue';
import { Link, router } from '@inertiajs/vue3';

defineProps<{
    error: 'spotify_not_connected' | 'spotify_reconnect_required' | 'rate_limited' | 'spotify_unavailable' | 'playlist_unavailable';
}>();
</script>

<template>
    <div data-part="library-error" class="flex flex-col items-start gap-3">
        <template v-if="error === 'spotify_not_connected' || error === 'spotify_reconnect_required'">
            <p data-part="library-message">
                {{
                    error === 'spotify_not_connected'
                        ? 'Connect Spotify to see your library.'
                        : 'Your Spotify access expired or was revoked. Please reconnect Spotify.'
                }}
            </p>
            <!-- A full page navigation: the answer is a redirect to Spotify. -->
            <form method="get" action="/spotify/connect">
                <AeroButton id="library-connect" type="submit">
                    {{ error === 'spotify_not_connected' ? 'Connect Spotify' : 'Reconnect Spotify' }}
                </AeroButton>
            </form>
        </template>

        <p v-else-if="error === 'rate_limited'" data-part="library-message">Spotify is busy right now. Try again in a moment.</p>

        <template v-else-if="error === 'playlist_unavailable'">
            <p data-part="library-message">This playlist is no longer available.</p>
            <Link href="/library" class="library-state__link" data-part="back-to-library">Back to your library</Link>
        </template>

        <template v-else>
            <p data-part="library-message" class="library-state__error">Couldn't load your Spotify library.</p>
            <AeroButton id="library-try-again" @click="router.reload()">Try again</AeroButton>
        </template>
    </div>
</template>

<style scoped>
.library-state__error {
    color: var(--aero-error);
}

.library-state__link {
    display: inline-flex;
    align-items: center;
    min-height: var(--aero-touch-min);
    padding: 0 var(--aero-space-2);
    color: var(--aero-text);
    font-family: var(--aero-font-family);
    text-decoration: underline;
}
</style>
