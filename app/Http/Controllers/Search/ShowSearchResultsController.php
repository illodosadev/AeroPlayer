<?php

namespace App\Http\Controllers\Search;

use App\Application\Search\Queries\SearchMusic;
use App\Application\Search\Results\SectionPage;
use App\Application\Search\SearchSection;
use App\Application\Spotify\Exceptions\SpotifyApiFailed;
use App\Bus\QueryBus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Spotify\Concerns\RespondsWithSpotifyErrors;
use App\Http\Requests\Search\SearchResultsRequest;
use App\Http\Search\SearchCursor;
use Illuminate\Http\JsonResponse;

/**
 * Search results in the app's common format (spotify-search-api, design D4): the three sections of a first search,
 * or one section's next page. Always JSON and never cached; Spotify failures use the player's error codes.
 */
class ShowSearchResultsController extends Controller
{
    use RespondsWithSpotifyErrors;

    public function __construct(private readonly QueryBus $queries) {}

    public function __invoke(SearchResultsRequest $request): JsonResponse
    {
        $query = $request->searchQuery();
        $section = $request->section();

        try {
            $results = $this->queries->ask(new SearchMusic($query, $section, $request->offset()));
        } catch (SpotifyApiFailed $failure) {
            return $this->spotifyError($failure);
        }

        if ($section !== null) {
            return response()->json($this->section($section, $results->page($section), $query), 200, $this->noStore());
        }

        $body = [];

        foreach (SearchSection::cases() as $each) {
            $body[$each->value] = $this->section($each, $results->page($each), $query);
        }

        return response()->json($body, 200, $this->noStore());
    }

    /**
     * One section as JSON: its items and the opaque cursor of its next page, or null.
     *
     * @return array{items: list<array<string, mixed>>, next_cursor: string|null}
     */
    private function section(SearchSection $section, SectionPage $page, string $query): array
    {
        return [
            'items' => array_map(fn ($item) => $item->toArray(), $page->items),
            'next_cursor' => $page->nextOffset === null ? null : SearchCursor::encode($section, $query, $page->nextOffset),
        ];
    }
}
