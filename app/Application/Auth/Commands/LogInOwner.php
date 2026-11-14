<?php

namespace App\Application\Auth\Commands;

use App\Bus\Command;

/**
 * Sign the owner in with email and password, under the login rate limit (owner-auth). The email is already
 * validated and in lowercase; the ip is part of the rate limit key.
 */
final readonly class LogInOwner implements Command
{
    public function __construct(
        public string $email,
        #[\SensitiveParameter] public string $password,
        public bool $remember,
        public string $ip,
    ) {}
}
