<?php

namespace App\Application\Owner\Queries;

use App\Application\Owner\Results\OwnerAccount;
use App\Repositories\UserRepository;

final readonly class GetOwnerAccountHandler
{
    public function __construct(private UserRepository $users) {}

    public function handle(GetOwnerAccount $query): ?OwnerAccount
    {
        $owner = $this->users->findOwner();

        return $owner ? new OwnerAccount($owner->email) : null;
    }
}
