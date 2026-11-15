<?php

namespace App\Application\YouTubeMusic\Queries;

use App\Bus\Query;

/**
 * The YouTube Music connection status for the shared Inertia prop. Null means not connected.
 */
final readonly class GetYouTubeMusicStatus implements Query {}
