<script setup lang="ts">
// The Liked Songs page (spotify-library-tracks, design D5): the saved songs 50 at a time, newest first. Saved songs
// have no Spotify context, so a song plays with the playable songs its loaded page shows, from that song.
import AeroBackground from '@/components/aero/AeroBackground.vue';
import AeroButton from '@/components/aero/AeroButton.vue';
import AeroCover from '@/components/aero/AeroCover.vue';
import AeroGlassPanel from '@/components/aero/AeroGlassPanel.vue';
import LibraryState from '@/components/library/LibraryState.vue';
import SongRow, { type SongRowSong } from '@/components/SongRow.vue';
import { usePagedSongs } from '@/composables/usePagedSongs';
import { useSpotifyPlayer } from '@/composables/useSpotifyPlayer';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type LikedSong = SongRowSong & { added_at: string };

const props = defineProps<{
    allowed: boolean;
    songs: LikedSong[] | null;
    total: number | null;
    nextCursor: string | null;
    error: 'spotify_not_connected' | 'spotify_reconnect_required' | 'rate_limited' | 'spotify_unavailable' | null;
}>();

const PLAYBACK_FAILED = "Playback couldn't start. Try again in a moment.";

const player = useSpotifyPlayer();
const playMessage = ref<string | null>(null);

const paged = usePagedSongs<LikedSong>({
    initial: props.songs ?? [],
    cursor: props.nextCursor,
    endpoint: '/library/liked/songs',
    key: (song) => song.id,
});

const empty = computed(() => props.songs !== null && props.songs.length === 0);

function songs(count: number): string {
    return `${count.toLocaleString('en-US')} ${count === 1 ? 'song' : 'songs'}`;
}

async function playSong(song: LikedSong): Promise<void> {
    if (!song.playable) {
        return;
    }

    // The playable songs of the loaded page that shows this song, in order.
    const page = paged.pages.value.find((songsOfPage) => songsOfPage.includes(song)) ?? [song];
    const uris = page.filter((each) => each.playable).map((each) => each.uri);

    playMessage.value = null;

    try {
        await player.play({ uris, offset: { position: uris.indexOf(song.uri) } });
    } catch {
        playMessage.value = PLAYBACK_FAILED;
    }
}
</script>

<template>
    <Head title="Liked Songs" />

    <AeroBackground>
        <main id="library-liked-page" class="mx-auto flex min-h-svh w-full max-w-2xl flex-col gap-3 p-4 sm:p-6">
            <Link href="/library" class="library-page__back self-start">Back</Link>

            <AeroGlassPanel>
                <LibraryState v-if="error !== null" :error="error" />

                <template v-else>
                    <header class="library-header">
                        <AeroCover :src="null" alt="Liked Songs" :size="96" />
                        <div class="library-header__text">
                            <h1 id="library-liked-title" class="library-header__title">Liked Songs</h1>
                            <p v-if="allowed && total !== null" class="library-header__detail" data-part="count">{{ songs(total) }}</p>
                        </div>
                    </header>

                    <div role="status" aria-live="polite" class="library-page__status">
                        <p v-if="playMessage" data-part="play-message" data-tone="error">{{ playMessage }}</p>
                    </div>

                    <div v-if="!allowed" data-part="liked-permission" class="flex flex-col items-start gap-3">
                        <p>Reconnect Spotify to grant access to your library.</p>
                        <form method="get" action="/spotify/connect">
                            <AeroButton id="library-reconnect-liked" type="submit">Reconnect Spotify</AeroButton>
                        </form>
                    </div>

                    <p v-else-if="empty" data-part="library-message">No liked songs yet.</p>

                    <template v-else>
                        <ul class="library-songs" aria-labelledby="library-liked-title">
                            <li v-for="song in paged.items.value" :key="song.id">
                                <SongRow :song="song" @play="playSong(song)" />
                            </li>
                        </ul>

                        <p v-if="paged.failed.value" role="status" data-part="section-error" data-tone="error" class="mt-2">
                            Couldn't load more songs. Try again in a moment.
                        </p>

                        <AeroButton
                            v-if="paged.nextCursor.value !== null"
                            data-part="load-more"
                            class="mt-2"
                            :disabled="paged.loading.value"
                            @click="paged.loadMore()"
                        >
                            Load more
                        </AeroButton>
                    </template>
                </template>
            </AeroGlassPanel>
        </main>
    </AeroBackground>
</template>

<style scoped>
.library-page__back {
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

.library-header {
    display: flex;
    gap: var(--aero-space-4);
    align-items: flex-start;
    margin-bottom: var(--aero-space-4);
}

.library-header__text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.library-header__title {
    overflow-wrap: anywhere;
    font-size: var(--aero-font-size-title);
    font-weight: 600;
}

.library-header__detail {
    color: var(--aero-text-muted);
    font-size: var(--aero-font-size-small);
}

.library-page__status:not(:empty) {
    margin-bottom: var(--aero-space-3);
}

[data-tone='error'] {
    color: var(--aero-error);
}

.library-songs {
    display: flex;
    flex-direction: column;
    gap: var(--aero-space-1);
}
</style>
