// "Load more" for a list of songs (spotify-library-tracks, design D5): the pages loaded so far, without repeated
// keys, and the opaque cursor of the next page. A playlist keys songs by position, since a song may repeat in it;
// Liked Songs keys them by id.
import { router } from '@inertiajs/vue3';
import { computed, ref, type Ref } from 'vue';

interface PageAnswer<T> {
    items: T[];
    next_cursor: string | null;
}

export function usePagedSongs<T>(options: { initial: T[]; cursor: string | null; endpoint: string; key: (song: T) => string | number }) {
    // Each page holds the songs it added, after dropping keys already shown, so a page never holds a song off screen.
    const pages = ref([options.initial]) as Ref<T[][]>;
    const nextCursor = ref<string | null>(options.cursor);
    const loading = ref(false);
    const failed = ref(false);

    const items = computed(() => pages.value.flat());

    async function loadMore(): Promise<void> {
        if (loading.value || nextCursor.value === null) {
            return;
        }

        loading.value = true;

        try {
            const response = await fetch(`${options.endpoint}?${new URLSearchParams({ cursor: nextCursor.value })}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (response.status === 401) {
                window.location.assign('/login');

                return;
            }

            // A cursor the server no longer reads (an APP_KEY change): start the page again.
            if (response.status === 422) {
                router.reload();

                return;
            }

            if (!response.ok) {
                failed.value = true;

                return;
            }

            const answer = (await response.json()) as PageAnswer<T>;
            const shown = new Set(items.value.map(options.key));

            pages.value.push(answer.items.filter((song) => !shown.has(options.key(song))));
            nextCursor.value = answer.next_cursor;
            failed.value = false;
        } catch {
            failed.value = true;
        } finally {
            loading.value = false;
        }
    }

    return { pages, items, nextCursor, loading, failed, loadMore };
}
