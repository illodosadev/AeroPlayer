<?php

namespace App\Application\Owner\Commands;

use App\Bus\Command;

/**
 * Create the owner account. The email is already validated and in lowercase.
 */
final readonly class CreateOwnerAccount implements Command
{
    public function __construct(
        public string $email,
        #[\SensitiveParameter] public string $password,
    ) {}
}
