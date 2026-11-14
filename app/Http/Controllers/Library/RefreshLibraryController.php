<?php

namespace App\Http\Controllers\Library;

use App\Application\Library\Commands\RefreshLibrary;
use App\Bus\CommandBus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * "Refresh" on the library page (spotify-library-overview, design D3): drop the kept library and show the page again,
 * which reads Spotify.
 */
class RefreshLibraryController extends Controller
{
    public function __construct(private readonly CommandBus $commands) {}

    public function __invoke(): RedirectResponse
    {
        $this->commands->dispatch(new RefreshLibrary);

        return redirect()->route('library');
    }
}
