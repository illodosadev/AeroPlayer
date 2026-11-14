<?php

namespace App\Application\Search\Results;

/**
 * A song in the app's common format (spotify-search-api, design D2). No field carries a service's own name.
 */
final readonly class SongResult
{
    /**
     * @param  list<string>  $artists  the artists' names
     * @param  string|null  $album  the album's title
     * @param  string|null  $image  the cover's URL
     */
    public function __construct(
        public string $source,
        public string $id,
        public string $uri,
        public string $title,
        public array $artists,
        public ?string $album,
        public int $durationMs,
        public ?string $image,
        public bool $playable,
    ) {}

    /**
     * @return array{source: string, id: string, uri: string, title: string, artists: list<string>, album: string|null, duration_ms: int, image: string|null, playable: bool}
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'id' => $this->id,
            'uri' => $this->uri,
            'title' => $this->title,
            'artists' => $this->artists,
            'album' => $this->album,
            'duration_ms' => $this->durationMs,
            'image' => $this->image,
            'playable' => $this->playable,
        ];
    }
}
