<?php

namespace App\Application\YouTubeMusic\Exceptions;

use App\Application\UseCaseFailed;

/**
 * Why the credentials service could not give an access token (youtube-music-credentials, design D3). Entry points
 * catch it, or one subclass, and map it to their own message or status. Messages are fixed texts.
 */
abstract class YouTubeMusicCredentialsFailed extends UseCaseFailed {}
