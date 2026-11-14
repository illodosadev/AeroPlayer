<script setup lang="ts">
// The library overview (spotify-library-overview, design D4): every playlist and the Liked Songs count in one list,
// with no tab per service, and the connection, permission, empty and failure states in order of precedence.
import AeroBackground from '@/components/aero/AeroBackground.vue';
import AeroButton from '@/components/aero/AeroButton.vue';
import AeroCover from '@/components/aero/AeroCover.vue';
import AeroGlassPanel from '@/components/aero/AeroGlassPanel.vue';
import { Head, Link, router } from '@inertiajs/vue3';
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

type LibraryError = 'spotify_not_connected' | 'spotify_reconnect_required' | 'rate_limited' | 'spotify_unavailable';

const props = defineProps<{
    playlists: Playlist[] | null;
    playlistsAllowed: boolean;
    likedTotal: number | null;
    likedAllowed: boolean;
    error: LibraryError | null;
}>();

const PERMISSION_TEXT = 'Reconnect Spotify to grant access to your library.';

// The label of each item's service; YouTube Music joins it in T-12.
const SOURCE_LABELS: Record<string, string> = { spotify: 'Spotify' };

const refreshing = ref(false);

// State 3 of the design: the empty texts apply only when both parts were read.
const bothRead = computed(() => props.error === null && props.playlistsAllowed && props.likedAllowed);
const empty = computed(() => bothRead.value && props.playlists?.length === 0 && props.likedTotal === 0);
const noPlaylists = computed(() => bothRead.value && props.playlists?.length === 0 && (props.likedTotal ?? 0) > 0);

function songs(count: number): string {
    return `${count.toLocaleString('en-US')} ${count === 1 ? 'song' : 'songs'}`;
}

/**
 * The line under a playlist's title: who owns it when the app cannot list its songs, otherwise its song count.
 */
function detail(playlist: Playlist): string | null {
    if (!playlist.can_list_tracks && playlist.owner) {
        return `by ${playlist.owner}`;
    }

    return playlist.total === null ? null : songs(playlist.total);
}

function sourceLabel(source: string): string {
    return SOURCE_LABELS[source] ?? source;
}

function refresh(): void {
    router.post(
        '/library/refresh',
        {},
        {
            preserveScroll: true,
            onStart: () => (refreshing.value = true),
            onFinish: () => (refreshing.value = false),
        },
    );
}

function tryAgain(): void {
    router.reload();
}
</script>

<template>
    <Head title="Library" />

    <AeroBackground>
        <main id="library-page" class="mx-auto flex min-h-svh w-full max-w-2xl flex-col gap-3 p-4 sm:p-6">
            <Link href="/dashboard" class="library-page__back self-start">Back</Link>

            <AeroGlassPanel>
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h1 id="library-title" class="text-xl font-semibold">Library</h1>
                    <AeroButton v-if="error === null" id="library-refresh" :disabled="refreshing" @click="refresh">Refresh</AeroButton>
                </div>

                <!-- 1. Errors: no library data at all. -->
                <div v-if="error !== null" data-part="library-error" class="flex flex-col items-start gap-3">
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

                    <template v-else>
                        <p data-part="library-message" data-tone="error">Couldn't load your Spotify library.</p>
                        <AeroButton id="library-try-again" @click="tryAgain">Try again</AeroButton>
                    </template>
                </div>

                <!-- 3. Nothing in either part. -->
                <p v-else-if="empty" data-part="library-message">Your library is empty.</p>

                <div v-else class="flex flex-col gap-6">
                    <!-- The Liked Songs entry opens its page (spotify-library-tracks, design D5). -->
                    <section aria-labelledby="library-liked-title" data-section="liked">
                        <Link href="/library/liked" class="library-row library-row--link" data-part="liked-entry">
                            <AeroCover :src="null" alt="Liked Songs" :size="48" />
                            <span class="library-row__text">
                                <span id="library-liked-title" class="library-row__title">Liked Songs</span>
                                <span v-if="likedAllowed && likedTotal !== null" class="library-row__detail" data-part="liked-count">
                                    {{ songs(likedTotal) }}
                                </span>
                                <span v-else-if="!likedAllowed" class="library-row__detail" data-part="liked-permission">{{ PERMISSION_TEXT }}</span>
                            </span>
                            <span class="library-row__meta">
                                <span class="library-row__source" data-part="source">{{ sourceLabel('spotify') }}</span>
                            </span>
                        </Link>
                    </section>

                    <section aria-labelledby="library-playlists-title" data-section="playlists">
                        <h2 id="library-playlists-title" class="library-section__title">Playlists</h2>

                        <!-- 2. The playlists permission is missing. -->
                        <div v-if="!playlistsAllowed" data-part="playlists-permission" class="flex flex-col items-start gap-3">
                            <p>{{ PERMISSION_TEXT }}</p>
                            <form method="get" action="/spotify/connect">
                                <AeroButton id="library-reconnect-playlists" type="submit">Reconnect Spotify</AeroButton>
                            </form>
                        </div>

                        <p v-else-if="noPlaylists" data-part="library-message">No playlists yet.</p>

                        <ul v-else class="library-section__list">
                            <li v-for="playlist in playlists ?? []" :key="playlist.id">
                                <!-- Each playlist opens its page (spotify-library-tracks, design D5). -->
                                <Link
                                    :href="`/library/playlists/${playlist.source}/${playlist.id}`"
                                    class="library-row library-row--link"
                                    data-part="playlist-row"
                                    :data-id="playlist.id"
                                >
                                    <AeroCover :src="playlist.image" :alt="`${playlist.title} cover`" :size="48" />
                                    <span class="library-row__text">
                                        <span class="library-row__title" data-part="title">{{ playlist.title }}</span>
                                        <span v-if="detail(playlist)" class="library-row__detail" data-part="detail">{{ detail(playlist) }}</span>
                                    </span>
                                    <span class="library-row__meta">
                                        <span v-if="playlist.collaborative" class="library-row__mark" data-part="collaborative">Collaborative</span>
                                        <span class="library-row__source" data-part="source">{{ sourceLabel(playlist.source) }}</span>
                                    </span>
                                </Link>
                            </li>
                        </ul>
                    </section>
                </div>
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

[data-tone='error'] {
    color: var(--aero-error);
}

.library-section__title {
    margin-bottom: var(--aero-space-2);
    font-size: var(--aero-font-size-title);
    font-weight: 600;
}

.library-section__list {
    display: flex;
    flex-direction: column;
    gap: var(--aero-space-1);
}

.library-row {
    display: flex;
    align-items: center;
    gap: var(--aero-space-3);
    min-height: var(--aero-touch-min);
    padding: var(--aero-space-1) var(--aero-space-2);
    border: 1px solid transparent;
    border-radius: var(--aero-radius-sm);
    color: var(--aero-text);
    font-family: var(--aero-font-family);
    font-size: var(--aero-font-size-body);
}

.library-row--link {
    text-decoration: none;
}

.library-row--link:hover {
    border-color: var(--aero-panel-border);
    background: var(--aero-field-bg);
}

.library-row__text {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
}

.library-row__title,
.library-row__detail {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.library-row__title {
    font-weight: 600;
}

.library-row__detail {
    color: var(--aero-text-muted);
    font-size: var(--aero-font-size-small);
}

.library-row__meta {
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    align-items: flex-end;
    gap: var(--aero-space-1);
    font-size: var(--aero-font-size-small);
}

.library-row__source,
.library-row__mark {
    padding: 0 var(--aero-space-2);
    border: 1px solid var(--aero-panel-border);
    border-radius: var(--aero-radius-pill);
    color: var(--aero-text);
}
</style>
