<?php

namespace App\Application\Search\Results;

use App\Application\Search\SearchSection;

/**
 * The answer of a search: the three sections, or the one section asked for (spotify-search-api, design D1).
 */
final readonly class SearchResults
{
    /**
     * @param  array<string, SectionPage>  $pages  keyed by SearchSection value, in the order of SearchSection::cases()
     */
    public function __construct(public array $pages) {}

    public function page(SearchSection $section): SectionPage
    {
        return $this->pages[$section->value];
    }
}
