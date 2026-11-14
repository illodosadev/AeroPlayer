<?php

namespace App\Application\Library\Exceptions;

use App\Application\UseCaseFailed;

/**
 * The service answered 404 or 403 for a playlist or its songs: it was deleted, unfollowed, or its collaboration was
 * withdrawn (spotify-library-tracks, design D1).
 */
final class PlaylistUnavailable extends UseCaseFailed
{
    public function __construct()
    {
        parent::__construct('This playlist is no longer available.');
    }
}
