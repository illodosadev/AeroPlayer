<?php

namespace App\Enums;

/**
 * The state of the stored YouTube Music connection. "not_connected" has no row, so it is not a case here.
 */
enum YouTubeMusicConnectionStatus: string
{
    case Connected = 'connected';
    case ReconnectRequired = 'reconnect_required';
}
