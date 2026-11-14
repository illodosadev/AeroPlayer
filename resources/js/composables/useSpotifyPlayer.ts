// The browser player (spotify-web-player, design D2–D10): one Spotify Web Playback SDK device per tab, its state for
// the player bar, and the controls. The state lives at module level, so every component reads the same player.
import { computed, reactive, readonly, ref } from 'vue';

export interface PlayerTrack {
    uri: string;
    title: string;
    artists: string[];
    album: string;
    image: string | null;
    durationMs: number;
}

export interface PlayRequest {
    contextUri?: string;
    uris?: string[];
    offset?: { position: number } | { uri: string };
}

/**
 * A non-2xx answer of the start endpoint, with its JSON error code (spotify-playback-api, design D5).
 */
export class PlayerRequestError extends Error {
    constructor(
        public readonly status: number,
        public readonly code: string | null,
    ) {
        super(code ?? `HTTP ${status}`);
    }
}

interface SdkTrack {
    uri: string;
    name: string;
    duration_ms?: number;
    artists?: { name: string }[];
    album?: { name?: string; images?: { url: string }[] };
}

interface SdkState {
    position: number;
    duration?: number;
    paused: boolean;
    timestamp: number;
    track_window?: { current_track?: SdkTrack | null; next_tracks?: SdkTrack[] };
}

interface SdkPlayer {
    connect(): Promise<boolean>;
    disconnect(): void;
    addListener(event: string, callback: (payload: never) => void): boolean;
    removeListener(event: string): boolean;
    pause(): Promise<void>;
    resume(): Promise<void>;
    seek(positionMs: number): Promise<void>;
    setVolume(volume: number): Promise<void>;
    nextTrack(): Promise<void>;
    previousTrack(): Promise<void>;
    activateElement(): Promise<void>;
}

interface SdkPlayerOptions {
    name: string;
    volume: number;
    getOAuthToken: (callback: (token: string) => void) => void;
}

declare global {
    interface Window {
        Spotify?: { Player: new (options: SdkPlayerOptions) => SdkPlayer };
        onSpotifyWebPlaybackSDKReady?: () => void;
        aeroPlayer?: { play: (request?: PlayRequest) => Promise<void> };
    }
}

const VOLUME_KEY = 'aeroplayer.volume';
const DEFAULT_VOLUME = 50;
const EVENTS = ['ready', 'not_ready', 'player_state_changed', 'autoplay_failed'];

/**
 * After this much of a track, "Previous" restarts it instead of moving to the previous track.
 */
const RESTART_AFTER_MS = 3000;

/**
 * The remembered volume, 0–100, or 50 when nothing valid is stored or storage is blocked (design D8).
 */
function readVolume(): number {
    try {
        const stored = Number(localStorage.getItem(VOLUME_KEY) ?? Number.NaN);

        return Number.isInteger(stored) && stored >= 0 && stored <= 100 ? stored : DEFAULT_VOLUME;
    } catch {
        return DEFAULT_VOLUME;
    }
}

function storeVolume(volume: number): void {
    try {
        localStorage.setItem(VOLUME_KEY, String(volume));
    } catch {
        // Storage can be blocked: the volume then lasts until the next reload.
    }
}

const state = reactive({
    deviceId: null as string | null,
    track: null as PlayerTrack | null,
    paused: true,
    positionMs: 0,
    timestamp: 0,
    hasNext: false,
    autoplayBlocked: false,
    volume: readVolume(),
    lastSeekAt: null as number | null,
});

const now = ref(Date.now());
let player: SdkPlayer | null = null;
let active = false;
let deviceName = '';
let scriptRequested = false;
let ticker: number | undefined;

/**
 * The elapsed time of the current track, interpolated between SDK states while it plays (design D7).
 */
const positionMs = computed(() => {
    if (!state.track) {
        return 0;
    }

    if (state.paused) {
        return state.positionMs;
    }

    return Math.min(state.track.durationMs, state.positionMs + Math.max(0, now.value - state.timestamp));
});

function startTicker(): void {
    if (ticker === undefined) {
        ticker = window.setInterval(() => (now.value = Date.now()), 250);
    }
}

function stopTicker(): void {
    if (ticker !== undefined) {
        window.clearInterval(ticker);
        ticker = undefined;
    }
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * A missing or expired session: the owner signs in again (design D4).
 */
function isSessionGone(status: number): boolean {
    if (status === 401 || status === 419) {
        window.location.assign('/login');

        return true;
    }

    return false;
}

async function fetchToken(): Promise<string | null> {
    const response = await fetch('/spotify/token', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

    if (isSessionGone(response.status) || !response.ok) {
        return null;
    }

    const body = (await response.json()) as { access_token?: unknown };

    return typeof body.access_token === 'string' ? body.access_token : null;
}

/**
 * Map the SDK's state to the bar's (design D6). Older states are ignored; a null state, when playback moved away, is
 * left for T-23.
 */
function onStateChanged(sdkState: SdkState | null): void {
    if (!sdkState || sdkState.timestamp < state.timestamp) {
        return;
    }

    const current = sdkState.track_window?.current_track;

    state.track = current
        ? {
              uri: current.uri,
              title: current.name,
              artists: (current.artists ?? []).map((artist) => artist.name),
              album: current.album?.name ?? '',
              image: current.album?.images?.[0]?.url ?? null,
              durationMs: sdkState.duration ?? current.duration_ms ?? 0,
          }
        : null;
    state.positionMs = sdkState.position;
    state.timestamp = sdkState.timestamp;
    state.paused = sdkState.paused;
    state.hasNext = (sdkState.track_window?.next_tracks?.length ?? 0) > 0;
    now.value = Date.now();

    if (sdkState.paused) {
        stopTicker();
    } else {
        state.autoplayBlocked = false;
        startTicker();
    }
}

function createPlayer(): void {
    if (!active || player || !window.Spotify) {
        return;
    }

    player = new window.Spotify.Player({
        name: deviceName,
        volume: state.volume / 100,
        getOAuthToken: (callback) => {
            void fetchToken().then((token) => {
                if (token !== null) {
                    callback(token);
                }
            });
        },
    });

    player.addListener('ready', ({ device_id }: { device_id: string }) => (state.deviceId = device_id));
    player.addListener('not_ready', () => (state.deviceId = null));
    player.addListener('player_state_changed', onStateChanged);
    player.addListener('autoplay_failed', () => (state.autoplayBlocked = true));
    void player.connect();

    // For the browser tests and for debugging; features import the composable (design D5).
    window.aeroPlayer = { play };
}

/**
 * Load the SDK and create the device, once per tab. Without an SDK URL nothing is loaded (design D3, D11).
 */
function start(sdkUrl: string, name: string): void {
    if (active || !sdkUrl) {
        return;
    }

    active = true;
    deviceName = name;

    if (window.Spotify) {
        createPlayer();

        return;
    }

    window.onSpotifyWebPlaybackSDKReady = createPlayer;

    if (!scriptRequested) {
        scriptRequested = true;

        const script = document.createElement('script');
        script.src = sdkUrl;
        script.async = true;
        script.dataset.spotifySdk = '';
        document.head.appendChild(script);
    }
}

/**
 * Disconnect the device and forget the playback state, keeping the volume (design D10).
 */
function stop(): void {
    active = false;

    if (player) {
        const disconnected = player;
        player = null;
        disconnected.disconnect();
        EVENTS.forEach((event) => disconnected.removeListener(event));
    }

    stopTicker();
    delete window.aeroPlayer;

    Object.assign(state, {
        deviceId: null,
        track: null,
        paused: true,
        positionMs: 0,
        timestamp: 0,
        hasNext: false,
        autoplayBlocked: false,
        lastSeekAt: null,
    });
}

/**
 * "Play" resumes, after activateElement() so a blocked autoplay starts; "Pause" pauses (design D9).
 */
async function togglePlay(): Promise<void> {
    if (!player || state.deviceId === null) {
        return;
    }

    if (state.autoplayBlocked || state.paused) {
        await player.activateElement();
        await player.resume();
    } else {
        await player.pause();
    }
}

/**
 * Seek to a point of the track and show it at once, without waiting for the next SDK state (design D7).
 */
function seek(positionMs: number): void {
    if (!player || !state.track) {
        return;
    }

    const target = Math.max(0, Math.min(state.track.durationMs, Math.round(positionMs)));

    void player.seek(target);
    state.positionMs = target;
    state.timestamp = Date.now();
    state.lastSeekAt = state.timestamp;
    now.value = state.timestamp;
}

/**
 * Set the volume, 0–100, remember it and pass it to the SDK as 0–1 (design D8).
 */
function setVolume(percent: number): void {
    const volume = Math.max(0, Math.min(100, Math.round(percent)));

    state.volume = volume;
    storeVolume(volume);
    void player?.setVolume(volume / 100);
}

function next(): void {
    if (player && state.hasNext) {
        void player.nextTrack();
    }
}

/**
 * Classic players' "Previous": restart the track after 3 seconds, otherwise go to the previous track. The SDK's
 * previousTrack() alone always moves back, whatever the position (design D2, manual check).
 */
function previous(): void {
    if (!player) {
        return;
    }

    if (state.track && positionMs.value > RESTART_AFTER_MS) {
        seek(0);
    } else {
        void player.previousTrack();
    }
}

/**
 * Start a context or tracks on this device through the server (design D5). Called from a click, so it unlocks audio
 * first with activateElement().
 */
async function play(request: PlayRequest = {}): Promise<void> {
    const hasContext = typeof request.contextUri === 'string';
    const hasUris = Array.isArray(request.uris);

    if (hasContext === hasUris) {
        throw new Error('play() needs exactly one of contextUri or uris');
    }

    if (!player || state.deviceId === null) {
        throw new Error('The Spotify player is not ready');
    }

    await player.activateElement();

    const response = await fetch('/spotify/player/play', {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify({
            device_id: state.deviceId,
            ...(hasContext ? { context_uri: request.contextUri } : { uris: request.uris }),
            offset: request.offset ?? { position: 0 },
        }),
    });

    if (!response.ok) {
        isSessionGone(response.status);

        let code: string | null = null;

        try {
            code = ((await response.json()) as { error?: string }).error ?? null;
        } catch {
            // An empty or non-JSON body keeps the code null.
        }

        throw new PlayerRequestError(response.status, code);
    }
}

export function useSpotifyPlayer() {
    return {
        state: readonly(state),
        positionMs,
        start,
        stop,
        togglePlay,
        seek,
        setVolume,
        next,
        previous,
        play,
    };
}
