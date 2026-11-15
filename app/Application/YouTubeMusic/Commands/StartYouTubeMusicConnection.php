<?php

namespace App\Application\YouTubeMusic\Commands;

use App\Bus\Command;

/**
 * Ask Google for a device code and start waiting for the owner's approval. Nothing happens while YouTube Music is
 * connected.
 */
final readonly class StartYouTubeMusicConnection implements Command {}
