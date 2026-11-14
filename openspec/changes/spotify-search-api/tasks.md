# Tasks

Every test fakes Spotify with `Http::preventStrayRequests()` and `Http::fake()` over `api.spotify.com/v1/search*`. No test reaches Spotify.

## 1. The search context

- [ ] 1.1 Add the `Search` context:
  - `SearchSection`;
  - the items `SongResult`, `AlbumResult` and `ArtistResult`;
  - `SectionPage` and `SearchResults`;
  - `App\Spotify\SpotifyCatalogMapper` (design D1, D2);
  - the fixtures in `tests/Fixtures/spotify/` (design D5).

  Verify in `tests/Unit/Search/SpotifyCatalogMapperTest.php`:
  - "A song with every detail";
  - "Missing images and playability";
  - the album year from `release_date`;
  - `null` items are skipped;
  - the exact keys of each item.
- [ ] 1.2 Add the query `SearchMusic` and its handler (design D1, D4). Verify that the reflection test in `tests/Unit/ArchitectureTest.php` accepts it.

## 2. Cursor and endpoint

- [ ] 2.1 Add `App\Http\Search\SearchCursor` (design D3). Verify in `tests/Feature/Search/SearchCursorTest.php` that a cursor:
  - round-trips;
  - is opaque, so the offset does not appear in it;
  - is rejected when it is altered, comes from another section or query, or carries an offset above 1000.
- [ ] 2.2 Add `SearchResultsRequest`, `Search/ShowSearchResultsController`, the route `GET search/results` named `search.results`, its row in `tests/Feature/RoutesTest.php`, and `search/results` in the JSON 401 of `bootstrap/app.php` (design D4). Verify in `tests/Feature/Search/SearchResultsTest.php`:
  - "Searching Spotify";
  - "Surrounding spaces do not count";
  - "Special characters reach Spotify unchanged";
  - "No results";
  - the three "common format" scenarios;
  - "An invalid query", all rows;
  - "Paging through songs";
  - "No more results";
  - "An invalid section or cursor", all rows;
  - "The search cannot be answered", all rows;
  - "The rate-limit pause covers the search";
  - "An expired token does not stop the search".

## 3. Integration

- [ ] 3.1 Run Pint on the changed PHP files. Then verify that `openspec validate spotify-search-api --strict`, `npm run build` and the full `vendor/bin/pest` run pass together.

## Workflow follow-up

- `/review-change spotify-search-api` before `/opsx-apply`.
- At `/ship`:
  - merge the `music-search` spec;
  - keep T-07 open;
  - apply the backlog follow-ups from design (T-07, T-08, T-11, T-22, T-24).
- Then `/opsx-propose spotify-search-page`.
