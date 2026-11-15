<?php

namespace App\Application\YouTubeMusic\Results;

/**
 * What the YouTube Music settings page shows (youtube-music-connection, design D7): display data only, never a
 * token, the device code or the channel id.
 */
final readonly class YouTubeMusicSettings
{
    /**
     * @param  'not_connected'|'connected'|'reconnect_required'  $status
     * @param  array{userCode: string, verificationUrl: string}|null  $waiting  the attempt waiting for approval
     */
    public function __construct(
        public bool $configured,
        public string $status,
        public ?string $channelName,
        public ?array $waiting,
    ) {}
}
