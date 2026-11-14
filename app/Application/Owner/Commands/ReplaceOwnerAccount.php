<?php

namespace App\Application\Owner\Commands;

use App\Bus\Command;

/**
 * Replace the owner's email and password, ending every other session of the account. The email is already
 * validated and in lowercase.
 */
final readonly class ReplaceOwnerAccount implements Command
{
    public function __construct(
        public string $email,
        #[\SensitiveParameter] public string $password,
    ) {}
}
