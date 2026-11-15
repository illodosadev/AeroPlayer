<?php

namespace App\Application\YouTubeMusic\Queries;

use App\Bus\Query;

/**
 * The YouTube Music settings page's state: configuration, connection and the attempt waiting for approval.
 */
final readonly class GetYouTubeMusicSettings implements Query {}
