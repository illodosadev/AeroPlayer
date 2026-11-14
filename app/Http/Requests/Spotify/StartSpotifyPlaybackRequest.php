<?php

namespace App\Http\Requests\Spotify;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates "PUT /spotify/player/play" (spotify-playback-api, design D4): a device, exactly one of a context URI or a
 * list of track URIs, and an optional offset by position or by URI. The URIs themselves are left to Spotify, which
 * answers 400 for a bad one.
 */
class StartSpotifyPlaybackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string'],
            'context_uri' => ['nullable', 'string', 'required_without:uris', 'prohibits:uris'],
            'uris' => ['nullable', 'array', 'list', 'required_without:context_uri'],
            'uris.*' => ['string'],
            // The offset rules apply only when an offset is sent; without one, playback starts at position 0.
            'offset' => ['sometimes', 'array:position,uri'],
            'offset.position' => ['sometimes', 'integer', 'min:0', 'prohibits:offset.uri'],
            'offset.uri' => ['sometimes', 'string'],
        ];
    }

    /**
     * The offset to send to Spotify: the validated one, or position 0.
     *
     * @return array{position: int}|array{uri: string}
     */
    public function offset(): array
    {
        $offset = (array) $this->validated('offset', []);

        if (isset($offset['uri'])) {
            return ['uri' => (string) $offset['uri']];
        }

        return ['position' => (int) ($offset['position'] ?? 0)];
    }
}
