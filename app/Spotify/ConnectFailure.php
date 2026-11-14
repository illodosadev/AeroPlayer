<?php

namespace App\Spotify;

/**
 * Why connecting Spotify failed, with the message the settings page shows (spotify-connection, design D2).
 */
enum ConnectFailure
{
    case Cancelled;
    case InvalidState;
    case ExchangeRejected;
    case NotAllowed;
    case NotOwner;
    case NotPremium;
    case Unavailable;

    public function message(): string
    {
        return match ($this) {
            self::Cancelled => 'Spotify connection was cancelled.',
            self::InvalidState, self::ExchangeRejected => 'Could not connect Spotify. Please try again.',
            self::NotAllowed => 'This Spotify account is not allowed to use this app.',
            self::NotOwner => "Only the owner's Spotify account can be connected.",
            self::NotPremium => 'Spotify Premium is required.',
            self::Unavailable => 'Spotify is not responding right now. Try again in a moment.',
        };
    }

    /**
     * The tone of the page message: a cancellation is information, every other outcome an error.
     */
    public function tone(): string
    {
        return $this === self::Cancelled ? 'info' : 'error';
    }
}
