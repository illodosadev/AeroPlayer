<?php

namespace App\Application\Search\Queries;

use App\Application\Search\Results\SearchResults;
use App\Application\Search\Results\SectionPage;
use App\Application\Search\SearchSection;
use App\Application\Spotify\Exceptions\SpotifyApiFailed;
use App\Application\Spotify\Exceptions\SpotifyUnavailable;
use App\Spotify\SpotifyCatalogMapper;
use App\Spotify\SpotifyClient;

final readonly class SearchMusicHandler
{
    public function __construct(
        private SpotifyClient $spotify,
        private SpotifyCatalogMapper $mapper,
    ) {}

    /**
     * Send one "GET /search" for the sections asked for and map each paging object (design D1 and D4). Spotify is the
     * only source for now; T-11 adds YouTube Music here without changing the results.
     *
     * @throws SpotifyApiFailed
     */
    public function handle(SearchMusic $query): SearchResults
    {
        $sections = $query->section !== null ? [$query->section] : SearchSection::cases();

        $response = $this->spotify->get('/search', [
            'q' => $query->query,
            'type' => implode(',', array_map(fn (SearchSection $section) => $section->spotifyType(), $sections)),
            'limit' => SearchMusic::PAGE_SIZE,
            'offset' => $query->offset,
            'market' => 'from_token',
        ]);

        // Any other refusal the client hands back, such as a 403 for an account outside the app's users.
        if (! $response->successful()) {
            throw new SpotifyUnavailable;
        }

        $pages = [];

        foreach ($sections as $section) {
            $paging = $response->json($section->spotifyKey());
            $pages[$section->value] = $this->page($section, is_array($paging) ? $paging : [], $query->offset);
        }

        return new SearchResults($pages);
    }

    /**
     * @param  array<string, mixed>  $paging  Spotify's paging object for the section
     */
    private function page(SearchSection $section, array $paging, int $offset): SectionPage
    {
        $items = is_array($paging['items'] ?? null) ? $paging['items'] : [];

        $mapped = match ($section) {
            SearchSection::Songs => $this->mapper->songs($items),
            SearchSection::Albums => $this->mapper->albums($items),
            SearchSection::Artists => $this->mapper->artists($items),
        };

        $nextOffset = $offset + SearchMusic::PAGE_SIZE;
        $hasNext = ($paging['next'] ?? null) !== null && $nextOffset < SearchMusic::MAX_OFFSET;

        return new SectionPage($mapped, $hasNext ? $nextOffset : null);
    }
}
