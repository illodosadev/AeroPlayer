<?php

namespace App\Http\Requests\PlaybackQueue;

/**
 * Validates "PATCH /queue" (playback-queue, design D5): the version and the song to make current. Changes 2 and 3 add
 * mode, shuffle, repeat and restart.
 */
class ChangeQueueCurrentRequest extends QueueRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->versionRules(),
            'current_item_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function currentItemId(): int
    {
        return (int) $this->input('current_item_id');
    }
}
