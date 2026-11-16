<?php

namespace App\Http\Requests\PlaybackQueue;

/**
 * Validates "DELETE /queue/items/{item}?version=" (playback-queue, design D5): the version the client holds.
 */
class RemoveQueueItemRequest extends QueueRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->versionRules();
    }
}
