<script setup lang="ts">
// The search page (spotify-search-page, design D1, D3, D4 and D5): the search field, the connection states and the
// songs, albums and artists sections with "Load more". The logic lives in useMusicSearch; the page only renders it.
import AeroBackground from '@/components/aero/AeroBackground.vue';
import AeroButton from '@/components/aero/AeroButton.vue';
import AeroCover from '@/components/aero/AeroCover.vue';
import AeroGlassPanel from '@/components/aero/AeroGlassPanel.vue';
import AeroInput from '@/components/aero/AeroInput.vue';
import SongRow from '@/components/SongRow.vue';
import { useMusicSearch, type SearchSectionName, type SongItem } from '@/composables/useMusicSearch';
import { useSpotifyPlayer } from '@/composables/useSpotifyPlayer';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';

const UNAVAILABLE = "Spotify search isn't available right now. Try again in a moment.";
const PLAYBACK_FAILED = "Playback couldn't start. Try again in a moment.";

const SECTION_TITLES: Record<SearchSectionName, string> = { songs: 'Songs', albums: 'Albums', artists: 'Artists' };

// The label of each result's service; YouTube Music joins it in T-11.
const SOURCE_LABELS: Record<string, string> = { spotify: 'Spotify' };

const page = usePage<SharedData>();
const spotifyStatus = page.props.spotify?.status;

const search = useMusicSearch(spotifyStatus === 'not_connected' || spotifyStatus === 'reconnect_required' ? spotifyStatus : 'idle');
const player = useSpotifyPlayer();
const playMessage = ref<string | null>(null);

const state = search.state;
const showField = computed(() => state.status !== 'not_connected' && state.status !== 'reconnect_required');
const hasResults = computed(() => state.sections.songs.items.length + state.sections.albums.items.length + state.sections.artists.items.length > 0);

const statusText = computed(() => {
    switch (state.status) {
        case 'idle':
            return 'Type to search your music.';
        case 'loading':
            return hasResults.value ? '' : 'Searching…';
        case 'empty':
            return `No results for "${state.searched}"`;
        case 'unavailable':
            return UNAVAILABLE;
        default:
            return '';
    }
});

function sourceLabel(source: string): string {
    return SOURCE_LABELS[source] ?? source;
}

/**
 * Play only this song on the browser's player (design D4). A song that is not playable is a disabled button, so this
 * runs for playable songs only; the check stays for safety.
 */
async function playSong(song: SongItem): Promise<void> {
    if (!song.playable) {
        return;
    }

    playMessage.value = null;

    try {
        await player.play({ uris: [song.uri] });
    } catch {
        playMessage.value = PLAYBACK_FAILED;
    }
}

onBeforeUnmount(() => search.dispose());
</script>

<template>
    <Head title="Search" />

    <AeroBackground>
        <main id="search-page" class="mx-auto flex min-h-svh w-full max-w-2xl flex-col gap-3 p-4 sm:p-6">
            <Link href="/dashboard" class="search-page__back self-start">Back</Link>

            <AeroGlassPanel>
                <h1 id="search-title" class="mb-4 text-xl font-semibold">Search</h1>

                <!-- No usable Spotify connection: no field, so no search can be sent (design D5). -->
                <div v-if="!showField" data-part="connect" class="flex flex-col items-start gap-3">
                    <p v-if="state.status === 'not_connected'" data-part="connect-message">Connect Spotify to search its catalog.</p>
                    <p v-else data-part="connect-message">Spotify access was revoked.</p>

                    <!-- A full page navigation: the answer is a redirect to Spotify. -->
                    <form method="get" action="/spotify/connect">
                        <AeroButton id="search-connect" type="submit">
                            {{ state.status === 'not_connected' ? 'Connect Spotify' : 'Reconnect Spotify' }}
                        </AeroButton>
                    </form>
                </div>

                <template v-else>
                    <form role="search" @submit.prevent="search.submit">
                        <AeroInput
                            id="search-query"
                            label="Search songs, albums and artists"
                            type="search"
                            autocomplete="off"
                            :maxlength="200"
                            :model-value="state.query"
                            @update:model-value="search.input"
                        />
                    </form>

                    <div role="status" aria-live="polite" class="search-page__status">
                        <p v-if="statusText" data-part="search-status" :data-tone="state.status === 'unavailable' ? 'error' : undefined">
                            {{ statusText }}
                        </p>
                        <p v-if="playMessage" data-part="play-message" data-tone="error">{{ playMessage }}</p>
                    </div>

                    <div v-if="hasResults && (state.status === 'results' || state.status === 'loading')" class="flex flex-col gap-6">
                        <section
                            v-for="name in ['songs', 'albums', 'artists'] as const"
                            v-show="state.sections[name].items.length > 0"
                            :key="name"
                            :data-section="name"
                            :aria-labelledby="`search-section-${name}`"
                            class="search-section"
                        >
                            <h2 :id="`search-section-${name}`" class="search-section__title">{{ SECTION_TITLES[name] }}</h2>

                            <ul class="search-section__list">
                                <template v-if="name === 'songs'">
                                    <li v-for="song in state.sections.songs.items" :key="song.id">
                                        <SongRow :song="song" @play="playSong(song)" />
                                    </li>
                                </template>

                                <template v-else-if="name === 'albums'">
                                    <li
                                        v-for="album in state.sections.albums.items"
                                        :key="album.id"
                                        class="search-row"
                                        data-part="album-row"
                                        :data-id="album.id"
                                    >
                                        <AeroCover :src="album.image" :alt="`${album.title} cover`" :size="48" />
                                        <span class="search-row__text">
                                            <span class="search-row__title" data-part="title">{{ album.title }}</span>
                                            <span class="search-row__detail">
                                                <span data-part="artists">{{ album.artists.join(', ') }}</span>
                                                <template v-if="album.year">
                                                    · <span data-part="year">{{ album.year }}</span>
                                                </template>
                                            </span>
                                        </span>
                                        <span class="search-row__meta">
                                            <span class="search-row__source" data-part="source">{{ sourceLabel(album.source) }}</span>
                                        </span>
                                    </li>
                                </template>

                                <template v-else>
                                    <li
                                        v-for="artist in state.sections.artists.items"
                                        :key="artist.id"
                                        class="search-row"
                                        data-part="artist-row"
                                        :data-id="artist.id"
                                    >
                                        <AeroCover :src="artist.image" :alt="`${artist.title} picture`" :size="48" />
                                        <span class="search-row__text">
                                            <span class="search-row__title" data-part="title">{{ artist.title }}</span>
                                        </span>
                                        <span class="search-row__meta">
                                            <span class="search-row__source" data-part="source">{{ sourceLabel(artist.source) }}</span>
                                        </span>
                                    </li>
                                </template>
                            </ul>

                            <p
                                v-if="state.sections[name].failed"
                                role="status"
                                data-part="section-error"
                                data-tone="error"
                                class="search-section__error"
                            >
                                {{ UNAVAILABLE }}
                            </p>

                            <AeroButton
                                v-if="state.sections[name].nextCursor !== null"
                                data-part="load-more"
                                class="search-section__more"
                                :aria-describedby="`search-section-${name}`"
                                :disabled="state.sections[name].loading"
                                @click="search.loadMore(name)"
                            >
                                Load more
                            </AeroButton>
                        </section>
                    </div>
                </template>
            </AeroGlassPanel>
        </main>
    </AeroBackground>
</template>

<style scoped>
.search-page__back {
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

.search-page__status {
    margin: var(--aero-space-3) 0;
    color: var(--aero-text);
}

.search-page__status:empty {
    margin: 0;
}

[data-tone='error'] {
    color: var(--aero-error);
}

.search-section__title {
    margin-bottom: var(--aero-space-2);
    font-size: var(--aero-font-size-title);
    font-weight: 600;
}

.search-section__list {
    display: flex;
    flex-direction: column;
    gap: var(--aero-space-1);
}

.search-row {
    display: flex;
    align-items: center;
    gap: var(--aero-space-3);
    width: 100%;
    min-height: var(--aero-touch-min);
    padding: var(--aero-space-1) var(--aero-space-2);
    border: 1px solid transparent;
    border-radius: var(--aero-radius-sm);
    color: var(--aero-text);
    font-family: var(--aero-font-family);
    font-size: var(--aero-font-size-body);
    text-align: start;
}

.search-row__text {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
}

.search-row__title,
.search-row__detail {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.search-row__title {
    font-weight: 600;
}

.search-row__detail {
    color: var(--aero-text-muted);
    font-size: var(--aero-font-size-small);
}

.search-row__meta {
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    align-items: flex-end;
    gap: var(--aero-space-1);
    font-size: var(--aero-font-size-small);
}

.search-row__source {
    padding: 0 var(--aero-space-2);
    border: 1px solid var(--aero-panel-border);
    border-radius: var(--aero-radius-pill);
    color: var(--aero-text);
}

.search-section__error {
    margin-top: var(--aero-space-2);
}

.search-section__more {
    margin-top: var(--aero-space-2);
}
</style>
