<?php

namespace App\Enums;

/**
 * The state of the stored Spotify connection. "not_connected" has no row, so it is not a case here.
 */
enum SpotifyConnectionStatus: string
{
    case Connected = 'connected';
    case ReconnectRequired = 'reconnect_required';
}
