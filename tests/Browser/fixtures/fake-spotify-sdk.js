// A fake of Spotify's Web Playback SDK for the browser tests (spotify-web-player, design D12). It implements only the
// methods and events the player uses, named as in Spotify's reference, records every call, and never emits an event
// on its own: each test emits "ready", "player_state_changed" or "autoplay_failed" through window.__fakeSpotify.
(function () {
    const fake = {
        players: [],
        calls: [],
        listeners: {},
        player: null,
        emit(event, payload) {
            (fake.listeners[event] || []).forEach((callback) => callback(payload));
        },
        requestToken() {
            return new Promise((resolve) => fake.player.options.getOAuthToken(resolve));
        },
        callNames() {
            return fake.calls.map((call) => call.name);
        },
    };

    class Player {
        constructor(options) {
            this.options = options;
            fake.players.push({ name: options.name, volume: options.volume });
            fake.player = this;
        }

        record(name, args) {
            fake.calls.push({ name, args: Array.from(args) });
        }

        addListener(event, callback) {
            (fake.listeners[event] = fake.listeners[event] || []).push(callback);
            return true;
        }

        removeListener(event) {
            delete fake.listeners[event];
            return true;
        }

        connect() {
            this.record('connect', arguments);
            return Promise.resolve(true);
        }

        disconnect() {
            this.record('disconnect', arguments);
        }

        getCurrentState() {
            return Promise.resolve(null);
        }
    }

    for (const name of ['pause', 'resume', 'togglePlay', 'seek', 'setVolume', 'nextTrack', 'previousTrack', 'activateElement']) {
        Player.prototype[name] = function () {
            this.record(name, arguments);
            return Promise.resolve();
        };
    }

    window.__fakeSpotify = fake;
    window.Spotify = { Player };

    if (typeof window.onSpotifyWebPlaybackSDKReady === 'function') {
        window.onSpotifyWebPlaybackSDKReady();
    }
})();
