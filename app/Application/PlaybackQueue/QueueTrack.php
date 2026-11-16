<?php

namespace App\Application\PlaybackQueue;

/**
 * A song sent to the queue, already validated, in the app's common format plus its opaque origin (playback-queue,
 * design D4 and D5). playable is not kept: only playable songs get this far.
 */
final readonly class QueueTrack
{
    /**
     * The services whose songs the queue accepts. T-10 adds youtube_music.
     */
    public const SOURCES = ['spotify'];

    /**
     * @param  list<string>  $artists
     * @param  array<string, mixed>|null  $origin
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
        public ?array $origin,
    ) {}

    /**
     * Build a track from a validated song of a request. Keys the queue does not store are ignored.
     *
     * @param  array<string, mixed>  $song
     */
    public static function fromArray(array $song): self
    {
        return new self(
            (string) $song['source'],
            (string) $song['id'],
            (string) $song['uri'],
            (string) $song['title'],
            array_values(array_map('strval', (array) ($song['artists'] ?? []))),
            isset($song['album']) ? (string) $song['album'] : null,
            (int) $song['duration_ms'],
            isset($song['image']) ? (string) $song['image'] : null,
            isset($song['origin']) && is_array($song['origin']) ? $song['origin'] : null,
        );
    }
}
