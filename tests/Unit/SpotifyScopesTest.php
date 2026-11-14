<?php

// spotify-connection, task 1.1: the scopes the settings page checks for "Reconnect Spotify to grant the new permissions."

use App\Spotify\SpotifyScopes;

test('no scope is missing when every required scope is granted, in any order and spacing', function () {
    $granted = '  '.implode('   ', array_reverse(SpotifyScopes::REQUIRED)).' user-top-read ';

    expect(SpotifyScopes::missing($granted))->toBe([]);
});

test('the scopes not granted are reported', function () {
    $granted = implode(' ', array_diff(SpotifyScopes::REQUIRED, ['user-read-playback-state', 'streaming']));

    expect(SpotifyScopes::missing($granted))->toBe(['streaming', 'user-read-playback-state']);
});

test('every required scope is missing when nothing was granted', function () {
    expect(SpotifyScopes::missing(''))->toBe(SpotifyScopes::REQUIRED);
});
