<?php

namespace App\Application\Library\Results;

/**
 * A playlist in the app's common format (spotify-library-overview, design D2). No field carries a service's own name.
 */
final readonly class PlaylistResult
{
    /**
     * @param  string|null  $image  the cover's URL
     * @param  string|null  $owner  the owner's display name
     * @param  bool  $canListTracks  whether the service lets the app list its songs: own or collaborative
     * @param  int|null  $total  the service's song count, when it gives one
     */
    public function __construct(
        public string $source,
        public string $id,
        public string $uri,
        public string $title,
        public ?string $image,
        public ?string $owner,
        public bool $collaborative,
        public bool $canListTracks,
        public ?int $total,
    ) {}

    /**
     * Rebuild a playlist from toArray(), for the cached library (design D1).
     *
     * @param  array{source: string, id: string, uri: string, title: string, image: string|null, owner: string|null, collaborative: bool, can_list_tracks: bool, total: int|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            source: $data['source'],
            id: $data['id'],
            uri: $data['uri'],
            title: $data['title'],
            image: $data['image'],
            owner: $data['owner'],
            collaborative: $data['collaborative'],
            canListTracks: $data['can_list_tracks'],
            total: $data['total'],
        );
    }

    /**
     * @return array{source: string, id: string, uri: string, title: string, image: string|null, owner: string|null, collaborative: bool, can_list_tracks: bool, total: int|null}
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'id' => $this->id,
            'uri' => $this->uri,
            'title' => $this->title,
            'image' => $this->image,
            'owner' => $this->owner,
            'collaborative' => $this->collaborative,
            'can_list_tracks' => $this->canListTracks,
            'total' => $this->total,
        ];
    }
}
