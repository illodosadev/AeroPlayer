<?php

namespace App\Application\Owner\Exceptions;

use App\Application\UseCaseFailed;

final class OwnerAccountExists extends UseCaseFailed
{
    public function __construct()
    {
        parent::__construct('An owner account already exists.');
    }
}
