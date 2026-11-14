<?php

namespace App\Application\Spotify\Exceptions;

use App\Application\UseCaseFailed;

/**
 * A Spotify Web API call could not be made or answered (spotify-api-client, design D2). Handlers let it bubble; the
 * entry point catches it, or one of its four subclasses, and shows its own message.
 */
abstract class SpotifyApiFailed extends UseCaseFailed {}
