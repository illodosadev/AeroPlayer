<?php

namespace App\Application\Spotify\Commands;

use App\Bus\Command;

/**
 * Delete the owner's Spotify connection and its tokens. Nothing happens when there is none.
 */
final readonly class DisconnectSpotify implements Command
{
    public function __construct(public int $userId) {}
}
