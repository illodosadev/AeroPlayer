<?php

namespace App\Application\Auth\Exceptions;

use App\Application\UseCaseFailed;

final class TooManyLoginAttempts extends UseCaseFailed
{
    /**
     * @param  int  $seconds  until the next attempt is allowed
     */
    public function __construct(public readonly int $seconds)
    {
        parent::__construct('Too many login attempts.');
    }
}
