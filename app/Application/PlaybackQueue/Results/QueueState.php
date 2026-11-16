<?php

namespace App\Application\PlaybackQueue\Results;

/**
 * The owner's whole queue as "GET /queue" and every queue write return it (playback-queue, design D4). Every song is
 * listed, played ones included; the client derives played, current and pending from currentItemId.
 */
final readonly class QueueState
{
    /**
     * @param  list<QueueItemResult>  $items
     * @param  array{uri: string, title: string|null}|null  $context
     */
    public function __construct(
        public int $version,
        public ?int $currentItemId,
        public bool $shuffle,
        public string $repeat,
        public string $mode,
        public ?array $context,
        public bool $hasMore,
        public array $items,
    ) {}

    /**
     * The queue of an owner who never used it.
     */
    public static function empty(): self
    {
        return new self(0, null, false, 'off', 'queue', null, false, []);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'current_item_id' => $this->currentItemId,
            'shuffle' => $this->shuffle,
            'repeat' => $this->repeat,
            'mode' => $this->mode,
            'context' => $this->context,
            'has_more' => $this->hasMore,
            'items' => array_map(fn (QueueItemResult $item) => $item->toArray(), $this->items),
        ];
    }
}
