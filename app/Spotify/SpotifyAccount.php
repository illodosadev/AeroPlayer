<?php

namespace App\Spotify;

/**
 * The Spotify account behind a new connection, from "GET /v1/me". Spotify omits "product" in development mode.
 */
final readonly class SpotifyAccount
{
    public function __construct(
        public string $id,
        public ?string $displayName,
        public ?string $product,
    ) {}
}
