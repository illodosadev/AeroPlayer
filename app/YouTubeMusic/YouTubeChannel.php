<?php

namespace App\YouTubeMusic;

/**
 * The YouTube channel behind an approved device code, from "GET /youtube/v3/channels?mine=true".
 */
final readonly class YouTubeChannel
{
    public function __construct(
        public string $id,
        public ?string $title,
    ) {}
}
