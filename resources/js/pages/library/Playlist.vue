<script setup lang="ts">
// A playlist page (spotify-library-tracks, design D5): the playlist, its songs 50 at a time and "Play". Songs play in
// the playlist's Spotify context from their Spotify position; a followed playlist plays as a context with no offset.
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

interface Playlist {
    source: string;
    id: string;
    uri: string;
    title: string;
    image: string | null;
    owner: string | null;
    collaborative: boolean;
    can_list_tracks: boolean;
    total: number | null;
}

type PlaylistSong = SongRowSong & { position: number; local: boolean };

const props = defineProps<{
    playlist: Playlist | null;
    songs: PlaylistSong[] | null;
    nextCursor: string | null;
    error: 'spotify_not_connected' | 'spotify_reconnect_required' | 'rate_limited' | 'spotify_unavailable' | 'playlist_unavailable' | null;
}>();

const PLAYBACK_FAILED = "Playback couldn't start. Try again in a moment.";

const player = useSpotifyPlayer();
const playMessage = ref<string | null>(null);

const paged = usePagedSongs<PlaylistSong>({
    initial: props.songs ?? [],
    cursor: props.nextCursor,
    endpoint: `/library/playlists/spotify/${props.playlist?.id ?? ''}/songs`,
    key: (song) => song.position,
});

const followed = computed(() => props.playlist !== null && props.songs === null);
const empty = computed(() => props.songs !== null && props.songs.length === 0);

function songs(count: number): string {
    return `${count.toLocaleString('en-US')} ${count === 1 ? 'song' : 'songs'}`;
}

async function start(request: { contextUri: string; offset?: { position: number } }): Promise<void> {
    playMessage.value = null;

    try {
        await player.play(request);
    } catch {
        playMessage.value = PLAYBACK_FAILED;
    }
}

function playAll(): void {
    if (!props.playlist) {
        return;
    }

    // A followed playlist cannot be listed, so Spotify starts it where it chooses.
    void start(followed.value ? { contextUri: props.playlist.uri } : { contextUri: props.playlist.uri, offset: { position: 0 } });
}

function playSong(song: PlaylistSong): void {
    if (!props.playlist || !song.playable) {
        return;
    }

    void start({ contextUri: props.playlist.uri, offset: { position: song.position } });
}
</script>

<template>
    <Head :title="playlist?.title ?? 'Playlist'" />

    <AeroBackground>
        <main id="library-playlist-page" class="mx-auto flex min-h-svh w-full max-w-2xl flex-col gap-3 p-4 sm:p-6">
            <Link href="/library" class="library-page__back self-start">Back</Link>

            <AeroGlassPanel>
                <LibraryState v-if="error !== null" :error="error" />

                <template v-else-if="playlist">
                    <header class="library-header">
                        <AeroCover :src="playlist.image" :alt="`${playlist.title} cover`" :size="96" />
                        <div class="library-header__text">
                            <h1 id="library-playlist-title" class="library-header__title">{{ playlist.title }}</h1>
                            <p v-if="playlist.owner" class="library-header__detail" data-part="owner">by {{ playlist.owner }}</p>
                            <p v-if="playlist.total !== null" class="library-header__detail" data-part="count">{{ songs(playlist.total) }}</p>
                            <AeroButton id="library-play" class="mt-2 self-start" :disabled="empty" @click="playAll">Play</AeroButton>
                        </div>
                    </header>

                    <div role="status" aria-live="polite" class="library-page__status">
                        <p v-if="playMessage" data-part="play-message" data-tone="error">{{ playMessage }}</p>
                    </div>

                    <p v-if="followed" data-part="library-message">Spotify doesn't let this app list the songs of playlists you don't own.</p>
                    <p v-else-if="empty" data-part="library-message">This playlist is empty.</p>

                    <template v-else>
                        <ul class="library-songs" aria-labelledby="library-playlist-title">
                            <li v-for="song in paged.items.value" :key="song.position">
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
