<?php

namespace App\Application\Search\Results;

/**
 * An artist in the app's common format (spotify-search-api, design D2).
 */
final readonly class ArtistResult
{
    /**
     * @param  string|null  $image  the artist's picture URL
     */
    public function __construct(
        public string $source,
        public string $id,
        public string $uri,
        public string $title,
        public ?string $image,
    ) {}

    /**
     * @return array{source: string, id: string, uri: string, title: string, image: string|null}
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'id' => $this->id,
            'uri' => $this->uri,
            'title' => $this->title,
            'image' => $this->image,
        ];
    }
}
