// The search page's state (spotify-search-page, design D2): the debounce, cancelling and stale answers, paging with
// the opaque cursors of "GET /search/results", and the answer of every failure. The page only renders this state.
import { reactive } from 'vue';

export type SearchSectionName = 'songs' | 'albums' | 'artists';

export const SEARCH_SECTIONS: SearchSectionName[] = ['songs', 'albums', 'artists'];

/**
 * The wait after the last keystroke before a search is sent.
 */
export const SEARCH_DEBOUNCE_MS = 300;

export interface SongItem {
    source: string;
    id: string;
    uri: string;
    title: string;
    artists: string[];
    album: string | null;
    duration_ms: number;
    image: string | null;
    playable: boolean;
}

export interface AlbumItem {
    source: string;
    id: string;
    uri: string;
    title: string;
    artists: string[];
    year: number | null;
    image: string | null;
}

export interface ArtistItem {
    source: string;
    id: string;
    uri: string;
    title: string;
    image: string | null;
}

interface SectionItems {
    songs: SongItem;
    albums: AlbumItem;
    artists: ArtistItem;
}

export interface SectionState<T> {
    items: T[];
    nextCursor: string | null;
    loading: boolean;
    failed: boolean;
}

type Sections = { [K in SearchSectionName]: SectionState<SectionItems[K]> };

interface SectionAnswer<T> {
    items: T[];
    next_cursor: string | null;
}

type Answer<T> = { kind: 'ok'; body: T } | { kind: 'failed'; status: number; code: string | null } | { kind: 'network' } | { kind: 'aborted' };

export type SearchStatus = 'idle' | 'loading' | 'results' | 'empty' | 'unavailable' | 'not_connected' | 'reconnect_required';

function emptySections(): Sections {
    return {
        songs: { items: [], nextCursor: null, loading: false, failed: false },
        albums: { items: [], nextCursor: null, loading: false, failed: false },
        artists: { items: [], nextCursor: null, loading: false, failed: false },
    };
}

async function request<T>(params: Record<string, string>, signal: AbortSignal | undefined): Promise<Answer<T>> {
    let response: Response;

    try {
        response = await fetch(`/search/results?${new URLSearchParams(params)}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal,
        });
    } catch (error) {
        return error instanceof DOMException && error.name === 'AbortError' ? { kind: 'aborted' } : { kind: 'network' };
    }

    try {
        const body = await response.json();

        return response.ok ? { kind: 'ok', body: body as T } : { kind: 'failed', status: response.status, code: body?.error ?? null };
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return { kind: 'aborted' };
        }

        return response.ok ? { kind: 'network' } : { kind: 'failed', status: response.status, code: null };
    }
}

/**
 * @param initialStatus the shared Spotify status: a page without a usable connection never searches (design D5)
 */
export function useMusicSearch(initialStatus: 'idle' | 'not_connected' | 'reconnect_required' = 'idle') {
    const state = reactive({
        query: '',
        status: initialStatus as SearchStatus,
        // The trimmed query of the shown results, or of the search in progress.
        searched: '',
        sections: emptySections(),
    });

    let timer: ReturnType<typeof setTimeout> | null = null;
    let controller: AbortController | null = null;
    // Every search gets a new number; an answer for an older number is dropped.
    let generation = 0;

    const blocked = () => state.status === 'not_connected' || state.status === 'reconnect_required';

    function stopTimer(): void {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
    }

    function clear(status: SearchStatus): void {
        controller?.abort();
        controller = null;
        generation++;
        state.status = status;
        state.sections = emptySections();
    }

    /**
     * A failure that applies to the whole page, from a search or a "Load more".
     *
     * @return whether it was handled
     */
    function handlePageFailure(status: number, code: string | null): boolean {
        if (status === 401) {
            window.location.assign('/login');

            return true;
        }

        if (status === 409 && code === 'spotify_reconnect_required') {
            stopTimer();
            clear('reconnect_required');

            return true;
        }

        if (status === 409 && code === 'spotify_not_connected') {
            stopTimer();
            clear('not_connected');

            return true;
        }

        return false;
    }

    async function search(query: string): Promise<void> {
        controller?.abort();
        controller = new AbortController();
        const mine = ++generation;

        state.status = 'loading';
        state.searched = query;

        const answer = await request<Record<SearchSectionName, SectionAnswer<unknown>>>({ q: query }, controller.signal);

        if (mine !== generation || answer.kind === 'aborted') {
            return;
        }

        if (answer.kind === 'ok') {
            const sections = emptySections();

            for (const name of SEARCH_SECTIONS) {
                const page = answer.body[name];
                sections[name].items = (page?.items ?? []) as never[];
                sections[name].nextCursor = page?.next_cursor ?? null;
            }

            state.sections = sections;
            state.status = SEARCH_SECTIONS.every((name) => sections[name].items.length === 0) ? 'empty' : 'results';

            return;
        }

        if (answer.kind === 'failed' && handlePageFailure(answer.status, answer.code)) {
            return;
        }

        // A 429, a 502, any other answer or no answer: the previous results go, the next search is the retry.
        state.sections = emptySections();
        state.status = 'unavailable';
    }

    /**
     * The field changed: search 300 ms after the last change, or go back to the start for a blank field.
     */
    function input(text: string): void {
        state.query = text;
        stopTimer();

        if (blocked()) {
            return;
        }

        if (text.trim() === '') {
            clear('idle');
            state.searched = '';

            return;
        }

        timer = setTimeout(() => {
            timer = null;
            void search(state.query.trim());
        }, SEARCH_DEBOUNCE_MS);
    }

    /**
     * Enter: search at once. Cancelling the timer keeps the 300 ms wait from sending the same search again.
     */
    function submit(): void {
        stopTimer();

        if (blocked()) {
            return;
        }

        const query = state.query.trim();

        if (query === '') {
            clear('idle');
            state.searched = '';

            return;
        }

        void search(query);
    }

    /**
     * The next page of one section. A section already loading ignores the call, so a double click sends one request.
     */
    async function loadMore(name: SearchSectionName): Promise<void> {
        const section = state.sections[name];

        if (section.loading || section.nextCursor === null) {
            return;
        }

        const mine = generation;
        section.loading = true;

        const answer = await request<SectionAnswer<SectionItems[typeof name]>>(
            { q: state.searched, section: name, cursor: section.nextCursor },
            controller?.signal,
        );

        // A new search started meanwhile: its sections replaced this one.
        if (mine !== generation || answer.kind === 'aborted') {
            return;
        }

        section.loading = false;

        if (answer.kind === 'ok') {
            const shown = new Set(section.items.map((item) => item.id));
            const fresh = answer.body.items.filter((item) => !shown.has(item.id));

            section.items.push(...(fresh as never[]));
            section.nextCursor = answer.body.next_cursor;
            section.failed = false;

            return;
        }

        if (answer.kind === 'failed' && handlePageFailure(answer.status, answer.code)) {
            return;
        }

        // A cursor the server no longer reads (an APP_KEY change): start the search again.
        if (answer.kind === 'failed' && answer.status === 422) {
            void search(state.searched);

            return;
        }

        section.failed = true;
    }

    /**
     * Stop the timer and the request in progress when the page goes away.
     */
    function dispose(): void {
        stopTimer();
        controller?.abort();
        controller = null;
        generation++;
    }

    return { state, input, submit, loadMore, dispose };
}
