<?php

namespace App\Http\Controllers\YouTubeMusic\Concerns;

use App\YouTubeMusic\YouTubeMusicConnectFailure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The YouTube Music settings page's flashed message, shared by the YouTube Music controllers
 * (youtube-music-connection, design D3 and D7). The page reads it from the session.
 */
trait RedirectsToYouTubeMusicSettings
{
    protected function flashFailure(Request $request, YouTubeMusicConnectFailure $failure): void
    {
        $this->flashMessage($request, $failure->tone(), $failure->message());
    }

    protected function flashMessage(Request $request, string $tone, string $text, ?string $detail = null): void
    {
        $request->session()->flash('youtubeMusicMessage', array_filter(
            ['tone' => $tone, 'text' => $text, 'detail' => $detail],
            fn ($value) => $value !== null,
        ));
    }

    protected function toSettings(): RedirectResponse
    {
        return redirect('/settings/youtube-music');
    }
}
