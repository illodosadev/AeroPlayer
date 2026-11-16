<?php

namespace App\Http\Requests\PlaybackQueue;

/**
 * Validates "PATCH /queue/items/{item}" (playback-queue, design D5): the version and the position among the pending
 * songs. A position past them is the handler's check, since it depends on the stored queue.
 */
class MoveQueueItemRequest extends QueueRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->versionRules(),
            'position' => ['required', 'integer', 'min:0'],
        ];
    }

    public function position(): int
    {
        return (int) $this->input('position');
    }
}
