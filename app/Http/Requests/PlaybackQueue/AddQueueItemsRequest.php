<?php

namespace App\Http\Requests\PlaybackQueue;

use Illuminate\Validation\Validator;

/**
 * Validates "POST /queue/items" (playback-queue, design D5): the version the client holds and the songs to add.
 */
class AddQueueItemsRequest extends QueueRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...$this->versionRules(), ...$this->songRules()];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->checkSongs($validator)];
    }
}
