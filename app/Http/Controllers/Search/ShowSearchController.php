<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The search page (spotify-search-page, design D1). It has no props of its own: the connection state comes from the
 * shared "spotify" prop, and the results from "GET /search/results".
 */
class ShowSearchController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Search');
    }
}
