<?php

namespace App\Application\Auth\Exceptions;

use App\Application\UseCaseFailed;

/**
 * The email and password do not match an account. The login page shows one message for every failure, so this
 * says nothing about which part was wrong.
 */
final class InvalidCredentials extends UseCaseFailed
{
    public function __construct()
    {
        parent::__construct('The credentials do not match.');
    }
}
