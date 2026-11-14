<?php

namespace App\Http\Requests\Search;

use App\Application\Search\SearchSection;
use App\Http\Search\SearchCursor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates "GET /search/results" (spotify-search-api, design D4): a trimmed query of 1 to 200 characters, and either
 * no section or a section with a cursor the app made for that section and query.
 */
class SearchResultsRequest extends FormRequest
{
    /**
     * The decoded cursor's offset, set by the after hook.
     */
    private int $cursorOffset = 0;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('q'))) {
            $this->merge(['q' => trim($this->input('q'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'max:200'],
            'section' => ['nullable', 'string', Rule::enum(SearchSection::class)],
            'cursor' => ['nullable', 'string', 'required_with:section'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $cursor = $this->input('cursor');

            if ($validator->errors()->isNotEmpty() || $cursor === null) {
                return;
            }

            $section = $this->section();

            if ($section === null) {
                $validator->errors()->add('cursor', 'A cursor needs a section.');

                return;
            }

            $offset = SearchCursor::decode((string) $cursor, $section, $this->searchQuery());

            if ($offset === null) {
                $validator->errors()->add('cursor', 'The cursor is invalid.');

                return;
            }

            $this->cursorOffset = $offset;
        }];
    }

    public function searchQuery(): string
    {
        return (string) $this->input('q');
    }

    public function section(): ?SearchSection
    {
        return SearchSection::tryFrom((string) $this->input('section'));
    }

    /**
     * The offset of the validated cursor, or 0 for a first search.
     */
    public function offset(): int
    {
        return $this->cursorOffset;
    }
}
