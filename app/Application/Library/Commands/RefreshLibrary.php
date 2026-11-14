<?php

namespace App\Application\Library\Commands;

use App\Bus\Command;

/**
 * Drop the kept library of the current connection, so the next overview reads Spotify again
 * (spotify-library-overview, design D3).
 */
final readonly class RefreshLibrary implements Command {}
