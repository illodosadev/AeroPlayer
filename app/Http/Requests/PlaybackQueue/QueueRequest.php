<?php

namespace App\Http\Requests\PlaybackQueue;

use App\Application\PlaybackQueue\QueueLimit;
use App\Application\PlaybackQueue\QueueTrack;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The base of the queue API's requests (playback-queue, design D5). A failed validation answers 422 with one
 * "error" code, picked from the first failed field, instead of Laravel's {message, errors}.
 */
abstract class QueueRequest extends FormRequest
{
    /**
     * The most bytes an origin may take once encoded as JSON.
     */
    private const ORIGIN_MAX_BYTES = 1024;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * The version the client holds, for the writes that carry one.
     *
     * @return array<string, mixed>
     */
    protected function versionRules(): array
    {
        return ['version' => ['required', 'integer', 'min:0']];
    }

    /**
     * The rules of a list of songs in the common format. Other keys of a song are ignored.
     *
     * @return array<string, mixed>
     */
    protected function songRules(): array
    {
        return [
            'tracks' => ['required', 'array', 'list', 'max:'.QueueLimit::SONGS],
            'tracks.*' => ['required', 'array'],
            'tracks.*.source' => ['required', 'string', Rule::in(QueueTrack::SOURCES)],
            'tracks.*.uri' => ['required', 'string', 'regex:/^spotify:track:[A-Za-z0-9]{22}$/'],
            'tracks.*.id' => ['required', 'string'],
            'tracks.*.title' => ['required', 'string', 'max:500'],
            'tracks.*.artists' => ['present', 'array', 'list', 'max:50'],
            'tracks.*.artists.*' => ['string', 'max:500'],
            'tracks.*.album' => ['nullable', 'string', 'max:500'],
            'tracks.*.duration_ms' => ['required', 'integer', 'min:0'],
            'tracks.*.image' => ['nullable', 'string', 'url', 'max:2048'],
            'tracks.*.playable' => ['required', 'boolean', 'accepted'],
            'tracks.*.origin' => ['nullable', 'array'],
        ];
    }

    /**
     * The checks a rule cannot express: the id matches its uri, and the origin is an object with a text "type" of at
     * most 1 KB.
     */
    protected function checkSongs(Validator $validator): void
    {
        $tracks = $this->input('tracks');

        if (! is_array($tracks)) {
            return;
        }

        foreach ($tracks as $index => $song) {
            if (! is_array($song)) {
                continue;
            }

            $uri = $song['uri'] ?? null;

            if (is_string($uri) && ($song['id'] ?? null) !== substr($uri, (int) strrpos($uri, ':') + 1)) {
                $validator->errors()->add("tracks.{$index}.id", 'The id does not match the uri.');
            }

            $origin = $song['origin'] ?? null;

            if ($origin !== null && ! $this->validOrigin($origin)) {
                $validator->errors()->add("tracks.{$index}.origin", 'The origin is invalid.');
            }
        }
    }

    /**
     * The validated songs, as the queue stores them.
     *
     * @return list<QueueTrack>
     */
    public function tracks(): array
    {
        return array_map(
            fn (array $song) => QueueTrack::fromArray($song),
            array_values((array) $this->input('tracks', [])),
        );
    }

    public function version(): int
    {
        return (int) $this->input('version');
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new HttpResponseException(response()->json(
            ['error' => $this->errorCode($validator)],
            422,
            ['Cache-Control' => 'no-store, private'],
        ));
    }

    /**
     * The code of the first failed field (design D5).
     */
    private function errorCode(ValidatorContract $validator): string
    {
        $failed = $validator->failed();
        $key = [...array_keys($failed), ...$validator->errors()->keys()][0] ?? '';

        return match (true) {
            $key === 'tracks' => isset($failed['tracks']['Max']) ? 'queue_full' : 'tracks_required',
            str_starts_with($key, 'tracks.') => 'invalid_track',
            $key === 'current_index' => 'current_index_out_of_range',
            default => 'invalid_request',
        };
    }

    private function validOrigin(mixed $origin): bool
    {
        if (! is_array($origin) || array_is_list($origin) || ! is_string($origin['type'] ?? null)) {
            return false;
        }

        return strlen((string) json_encode($origin)) <= self::ORIGIN_MAX_BYTES;
    }
}
