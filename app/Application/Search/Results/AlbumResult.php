<?php

namespace App\Application\Search\Results;

/**
 * An album in the app's common format (spotify-search-api, design D2).
 */
final readonly class AlbumResult
{
    /**
     * @param  list<string>  $artists  the artists' names
     * @param  int|null  $year  the release year
     * @param  string|null  $image  the cover's URL
     */
    public function __construct(
        public string $source,
        public string $id,
        public string $uri,
        public string $title,
        public array $artists,
        public ?int $year,
        public ?string $image,
    ) {}

    /**
     * @return array{source: string, id: string, uri: string, title: string, artists: list<string>, year: int|null, image: string|null}
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'id' => $this->id,
            'uri' => $this->uri,
            'title' => $this->title,
            'artists' => $this->artists,
            'year' => $this->year,
            'image' => $this->image,
        ];
    }
}
