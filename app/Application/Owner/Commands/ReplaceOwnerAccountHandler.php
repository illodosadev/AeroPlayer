<?php

namespace App\Application\Owner\Commands;

use App\Application\Owner\Exceptions\OwnerAccountMissing;
use App\Repositories\UserRepository;

final readonly class ReplaceOwnerAccountHandler
{
    public function __construct(private UserRepository $users) {}

    /**
     * @throws OwnerAccountMissing
     */
    public function handle(ReplaceOwnerAccount $command): void
    {
        $owner = $this->users->findOwner() ?? throw new OwnerAccountMissing;

        $this->users->replaceOwner($owner, $command->email, $command->password);
    }
}
