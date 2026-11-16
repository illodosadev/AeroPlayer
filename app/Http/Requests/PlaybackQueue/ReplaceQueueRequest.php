<?php

namespace App\Http\Requests\PlaybackQueue;

use Illuminate\Validation\Validator;

/**
 * Validates "PUT /queue" (playback-queue, design D5): the songs and the index of the current one, 0 by default. It
 * carries no version.
 */
class ReplaceQueueRequest extends QueueRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->songRules(),
            'current_index' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $this->checkSongs($validator);

            $tracks = $this->input('tracks');

            if (is_array($tracks) && $tracks !== [] && $validator->errors()->isEmpty() && $this->currentIndex() >= count($tracks)) {
                $validator->errors()->add('current_index', 'The current index is outside the list.');
            }
        }];
    }

    public function currentIndex(): int
    {
        return (int) $this->input('current_index', 0);
    }
}
