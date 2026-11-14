<?php

namespace App\Application\Owner\Commands;

use App\Application\Owner\Exceptions\OwnerAccountExists;
use App\Repositories\UserRepository;

final readonly class CreateOwnerAccountHandler
{
    public function __construct(private UserRepository $users) {}

    /**
     * @throws OwnerAccountExists
     */
    public function handle(CreateOwnerAccount $command): void
    {
        if ($this->users->findOwner() !== null) {
            throw new OwnerAccountExists;
        }

        $this->users->createOwner($command->email, $command->password);
    }
}
