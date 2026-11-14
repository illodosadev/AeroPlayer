<?php

namespace App\Application\Auth\Commands;

use App\Bus\Command;

/**
 * Sign the owner out of the web guard. Ending the session itself is the controller's HTTP concern.
 */
final readonly class LogOutOwner implements Command {}
