<?php

namespace App\Application\Owner\Results;

/**
 * The owner account as the console shows it: the email only, never the password hash or the model.
 */
final readonly class OwnerAccount
{
    public function __construct(public string $email) {}
}
