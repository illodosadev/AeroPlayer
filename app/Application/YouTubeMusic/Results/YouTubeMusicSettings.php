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
     * @param  bool  $misconfigured  a renewal was refused for the client (youtube-music-credentials, design D5)
     */
    public function __construct(
        public bool $configured,
        public string $status,
        public ?string $channelName,
        public ?array $waiting,
        public bool $misconfigured = false,
    ) {}
}
