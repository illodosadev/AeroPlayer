<?php

namespace App\Http\Requests\Library;

use App\Http\Paging\LibraryCursor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates "Load more" on a playlist or Liked Songs (spotify-library-tracks, design D4): a cursor the app made for
 * this very list. A bad one answers 422 before anything is sent to Spotify.
 */
class LibrarySongsPageRequest extends FormRequest
{
    private int $cursorOffset = 0;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['cursor' => ['required', 'string']];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $offset = LibraryCursor::decode((string) $this->input('cursor'), $this->scope());

            if ($offset === null) {
                $validator->errors()->add('cursor', 'The cursor is invalid.');

                return;
            }

            $this->cursorOffset = $offset;
        }];
    }

    /**
     * The scope of the list this request pages: the playlist in the route, or Liked Songs.
     */
    public function scope(): string
    {
        $id = $this->route('id');

        return is_string($id) ? LibraryCursor::playlistScope($id) : LibraryCursor::LIKED_SCOPE;
    }

    public function offset(): int
    {
        return $this->cursorOffset;
    }
}
