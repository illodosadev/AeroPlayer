<?php

namespace App\Application\YouTubeMusic\Commands;

use App\Bus\Command;

/**
 * Try to revoke the stored token at Google, then delete the owner's YouTube Music connection and its tokens.
 * Nothing happens when there is none.
 */
final readonly class DisconnectYouTubeMusic implements Command
{
    public function __construct(public int $userId) {}
}
