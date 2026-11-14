<?php

namespace App\Http\Controllers\Spotify\Concerns;

use App\Spotify\ConnectFailure;
use Illuminate\Http\RedirectResponse;

/**
 * The redirect to the Spotify settings page with its message, shared by the connect and disconnect controllers
 * (single-action-controllers, design D2). The message is a session flash the page reads (spotify-connection,
 * design D8).
 */
trait RedirectsToSpotifySettings
{
    protected function failed(ConnectFailure $failure): RedirectResponse
    {
        return $this->toSettings($failure->tone(), $failure->message());
    }

    protected function toSettings(string $tone, string $text, ?string $detail = null): RedirectResponse
    {
        return redirect('/settings/spotify')->with('spotifyMessage', array_filter(
            ['tone' => $tone, 'text' => $text, 'detail' => $detail],
            fn ($value) => $value !== null,
        ));
    }
}
