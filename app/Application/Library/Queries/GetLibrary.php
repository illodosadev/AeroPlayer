<?php

namespace App\Application\Library\Queries;

use App\Application\Library\Results\LibraryOverview;
use App\Bus\Query;

/**
 * The owner's library overview: every playlist and the saved-song count (spotify-library-overview, design D1). It
 * keeps the answer for 10 minutes per connection; writing that cache is infrastructure, like the token refresh in
 * GetSpotifyPlaybackToken.
 *
 * @implements Query<LibraryOverview>
 */
final readonly class GetLibrary implements Query {}
