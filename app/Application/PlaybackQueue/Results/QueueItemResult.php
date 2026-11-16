<?php

namespace App\Application\PlaybackQueue\Results;

/**
 * One song of the queue as the API returns it (playback-queue, design D4): the common song fields, its item id and
 * its origin, exactly as sent.
 */
final readonly class QueueItemResult
{
    /**
     * @param  list<string>  $artists
     * @param  array<string, mixed>|null  $origin
     */
    public function __construct(
        public int $itemId,
        public string $source,
        public string $id,
        public string $uri,
        public string $title,
        public array $artists,
        public ?string $album,
        public int $durationMs,
        public ?string $image,
        public ?array $origin,
    ) {}

    /**
     * @return array{item_id: int, source: string, id: string, uri: string, title: string, artists: list<string>, album: string|null, duration_ms: int, image: string|null, origin: array<string, mixed>|null}
     */
    public function toArray(): array
    {
        return [
            'item_id' => $this->itemId,
            'source' => $this->source,
            'id' => $this->id,
            'uri' => $this->uri,
            'title' => $this->title,
            'artists' => $this->artists,
            'album' => $this->album,
            'duration_ms' => $this->durationMs,
            'image' => $this->image,
            'origin' => $this->origin,
        ];
    }
}
