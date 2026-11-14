<?php

namespace App\Application\Spotify\Commands;

use App\Bus\Command;

/**
 * Start playback on a Spotify device with exactly one of a context URI (album, playlist or artist) or a list of track
 * URIs, from an offset by position or by URI (spotify-playback-api, design D3).
 */
final readonly class StartSpotifyPlayback implements Command
{
    /**
     * @param  list<string>|null  $uris
     * @param  array{position: int}|array{uri: string}  $offset
     */
    public function __construct(
        public string $deviceId,
        public ?string $contextUri,
        public ?array $uris,
        public array $offset,
    ) {}
}
