// The owner's playback queue in this tab (playback-queue, design D8). The server holds the queue; this keeps the last
// state it accepted, sends the owner's edits one after another, and reads the queue again on load, when the tab
// becomes visible and every few seconds while it stays visible. State lives at module level: one queue per tab.
import { computed, nextTick, reactive, readonly, ref } from 'vue';

export interface QueueItem {
    item_id: number;
    source: string;
    id: string;
    uri: string;
    title: string;
    artists: string[];
    album: string | null;
    duration_ms: number;
    image: string | null;
    origin: Record<string, unknown> | null;
}

export interface QueueState {
    version: number;
    current_item_id: number | null;
    shuffle: boolean;
    repeat: string;
    mode: string;
    context: { uri: string; title: string | null } | null;
    has_more: boolean;
    items: QueueItem[];
}

/**
 * A song as the rows hold it: the common format, plus keys of their own that are not sent.
 */
export interface QueueSong {
    source: string;
    id: string;
    uri: string;
    title: string;
    artists: string[];
    album: string | null;
    duration_ms: number;
    image: string | null;
    playable: boolean;
    origin?: Record<string, unknown> | null;
}

export const QUEUE_MESSAGES = {
    added: 'Added to queue',
    full: 'The queue is full (500 songs). Remove some songs first.',
    conflict: 'The queue changed on another device.',
    failed: "Couldn't update the queue. Try again.",
} as const;

const MESSAGE_MS = 5000;

const state = reactive({
    queue: null as QueueState | null,
    pendingAdds: new Set<string>(),
    pendingItems: new Set<number>(),
});

const message = ref<string | null>(null);

let messageTimer: ReturnType<typeof setTimeout> | null = null;
let pollTimer: ReturnType<typeof setTimeout> | null = null;
let pollMs = 30000;
let syncing = false;
let chain: Promise<void> = Promise.resolve();

const currentIndex = computed(() => {
    const queue = state.queue;

    return queue && queue.current_item_id !== null ? queue.items.findIndex((item) => item.item_id === queue.current_item_id) : -1;
});

const current = computed<QueueItem | null>(() => (currentIndex.value >= 0 ? (state.queue?.items[currentIndex.value] ?? null) : null));

const upNext = computed<QueueItem[]>(() => (currentIndex.value >= 0 ? (state.queue?.items.slice(currentIndex.value + 1) ?? []) : []));

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function showMessage(text: string): void {
    if (messageTimer) {
        clearTimeout(messageTimer);
    }

    // The same text again is cleared first, so assistive technology announces it once more.
    message.value = null;
    void nextTick(() => {
        message.value = text;
        messageTimer = setTimeout(() => (message.value = null), MESSAGE_MS);
    });
}

/**
 * Keep the newest state: a poll that answers after an edit never brings back an older queue (design D8).
 */
function adopt(next: QueueState | undefined): void {
    if (next && (state.queue === null || next.version >= state.queue.version)) {
        state.queue = next;
    }
}

interface Answer {
    status: number;
    body: { error?: string; queue?: QueueState } & Partial<QueueState>;
}

/**
 * Send a request to the queue API. Null means the network failed.
 */
async function send(method: string, url: string, body?: unknown): Promise<Answer | null> {
    try {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...(body === undefined ? {} : { 'Content-Type': 'application/json' }),
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: body === undefined ? undefined : JSON.stringify(body),
        });

        let parsed: Answer['body'] = {};

        try {
            parsed = (await response.json()) as Answer['body'];
        } catch {
            // An empty or non-JSON body.
        }

        return { status: response.status, body: parsed };
    } catch {
        return null;
    }
}

function sessionGone(status: number): boolean {
    if (status === 401 || status === 419) {
        window.location.assign('/login');

        return true;
    }

    return false;
}

/**
 * Read the queue. A failed read changes nothing and says nothing: the next one tries again.
 */
async function load(): Promise<void> {
    const answer = await send('GET', '/queue');

    if (answer === null || sessionGone(answer.status)) {
        return;
    }

    if (answer.status === 200) {
        adopt(answer.body as QueueState);
    }
}

/**
 * Turn the answer to an edit into the new state and the owner's message (design D8).
 */
async function settle(answer: Answer | null, success?: string): Promise<void> {
    if (answer === null || answer.status >= 500) {
        showMessage(QUEUE_MESSAGES.failed);

        return;
    }

    if (sessionGone(answer.status)) {
        return;
    }

    if (answer.status === 200) {
        adopt(answer.body as QueueState);

        if (success) {
            showMessage(success);
        }

        return;
    }

    if (answer.status === 409) {
        adopt(answer.body.queue);
        showMessage(QUEUE_MESSAGES.conflict);

        return;
    }

    if (answer.status === 422 && answer.body.error === 'queue_full') {
        showMessage(QUEUE_MESSAGES.full);

        return;
    }

    await load();
    showMessage(QUEUE_MESSAGES.failed);
}

/**
 * Run an edit after the ones already asked for. Each reads the version when its turn comes (design D8).
 */
function enqueue(edit: () => Promise<void>): Promise<void> {
    const run = chain.then(async () => {
        if (state.queue === null) {
            await load();
        }

        await edit();
    });

    chain = run.catch(() => undefined);

    return chain;
}

function version(): number {
    return state.queue?.version ?? 0;
}

function hasItem(itemId: number): boolean {
    return state.queue?.items.some((item) => item.item_id === itemId) ?? false;
}

/**
 * Add a song at the end of the queue. A second press while it is queued or in flight is ignored.
 */
function add(song: QueueSong): Promise<void> {
    if (!song.playable || state.pendingAdds.has(song.uri)) {
        return Promise.resolve();
    }

    state.pendingAdds.add(song.uri);

    const track = {
        source: song.source,
        id: song.id,
        uri: song.uri,
        title: song.title,
        artists: song.artists,
        album: song.album,
        duration_ms: song.duration_ms,
        image: song.image,
        playable: song.playable,
        ...(song.origin ? { origin: song.origin } : {}),
    };

    return enqueue(async () => {
        try {
            await settle(await send('POST', '/queue/items', { version: version(), tracks: [track] }), QUEUE_MESSAGES.added);
        } finally {
            state.pendingAdds.delete(song.uri);
        }
    });
}

/**
 * Run an edit of one queue item, unless one is already pending for it, and drop it when the item is gone by its turn.
 */
function editItem(itemId: number, request: () => Promise<Answer | null>): Promise<void> {
    if (state.pendingItems.has(itemId)) {
        return Promise.resolve();
    }

    state.pendingItems.add(itemId);

    return enqueue(async () => {
        try {
            if (hasItem(itemId)) {
                await settle(await request());
            }
        } finally {
            state.pendingItems.delete(itemId);
        }
    });
}

/**
 * Move a pending song to a position among the pending songs, counted from 0.
 */
function move(itemId: number, position: number): Promise<void> {
    return editItem(itemId, () => send('PATCH', `/queue/items/${itemId}`, { version: version(), position }));
}

function remove(itemId: number): Promise<void> {
    return editItem(itemId, () => send('DELETE', `/queue/items/${itemId}?version=${version()}`));
}

function clearPoll(): void {
    if (pollTimer) {
        clearTimeout(pollTimer);
        pollTimer = null;
    }
}

function schedulePoll(): void {
    clearPoll();

    if (syncing && document.visibilityState === 'visible') {
        pollTimer = setTimeout(async () => {
            await load();
            schedulePoll();
        }, pollMs);
    }
}

function onVisibilityChange(): void {
    if (document.visibilityState === 'visible') {
        void load();
        schedulePoll();
    } else {
        clearPoll();
    }
}

/**
 * Read the queue now and keep it in sync while the tab is visible (design D8).
 */
function startSync(pollSeconds: number): void {
    if (syncing) {
        return;
    }

    syncing = true;
    pollMs = Math.max(1, pollSeconds) * 1000;
    void load();
    schedulePoll();
    document.addEventListener('visibilitychange', onVisibilityChange);
}

/**
 * Stop syncing and forget the queue, such as after logging out.
 */
function stopSync(): void {
    syncing = false;
    clearPoll();
    document.removeEventListener('visibilitychange', onVisibilityChange);
    state.queue = null;
    state.pendingAdds.clear();
    state.pendingItems.clear();
    message.value = null;
}

export function useQueue() {
    return {
        state: readonly(state),
        current,
        upNext,
        message: readonly(message),
        load,
        add,
        move,
        remove,
        startSync,
        stopSync,
        isAddPending: (uri: string) => state.pendingAdds.has(uri),
        isItemPending: (itemId: number) => state.pendingItems.has(itemId),
    };
}
