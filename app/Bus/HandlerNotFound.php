<?php

namespace App\Bus;

use LogicException;

/**
 * A command or query has no "<Message>Handler" class. The message names the class only, never the payload, which
 * can hold a password.
 */
final class HandlerNotFound extends LogicException
{
    public static function for(object $message): self
    {
        return new self('No handler for '.$message::class.': expected the class '.$message::class.'Handler.');
    }
}
