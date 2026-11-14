<?php

namespace App\Application\Owner\Exceptions;

use App\Application\UseCaseFailed;

final class OwnerAccountMissing extends UseCaseFailed
{
    public function __construct()
    {
        parent::__construct('There is no owner account to replace.');
    }
}
