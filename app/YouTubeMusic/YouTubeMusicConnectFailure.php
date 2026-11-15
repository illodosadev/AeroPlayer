<?php

namespace App\YouTubeMusic;

/**
 * Why connecting YouTube Music failed, with the status the polling endpoint answers and the message the settings
 * page shows (youtube-music-connection, design D3).
 */
enum YouTubeMusicConnectFailure
{
    case Unreachable;
    case Misconfigured;
    case Denied;
    case Expired;
    case NotOwner;
    case ChannelUnverifiable;
    case Rejected;

    /**
     * The status "GET /youtube-music/connect/status" answers when an attempt ends this way.
     */
    public function status(): string
    {
        return match ($this) {
            self::Denied => 'denied',
            self::Expired => 'expired',
            self::NotOwner => 'not_owner',
            self::Unreachable, self::Misconfigured, self::ChannelUnverifiable, self::Rejected => 'failed',
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::Unreachable => "Couldn't reach Google. Try again in a moment.",
            self::Misconfigured => 'YouTube Music is not configured correctly.',
            self::Denied => 'You denied access to YouTube Music.',
            self::Expired => 'The code expired. Start again.',
            self::NotOwner => "This YouTube account isn't yours. Nothing was connected.",
            self::ChannelUnverifiable => "Couldn't check your YouTube account. Try again.",
            self::Rejected => "Couldn't connect YouTube Music. Try again.",
        };
    }

    /**
     * The tone of the page message: a denial or an expired code is information, every other outcome an error.
     */
    public function tone(): string
    {
        return in_array($this, [self::Denied, self::Expired], true) ? 'info' : 'error';
    }
}
