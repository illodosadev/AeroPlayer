<?php

namespace App\Application\Owner\Queries;

use App\Application\Owner\Results\OwnerAccount;
use App\Bus\Query;

/**
 * The single owner account, or null before it is created.
 *
 * @implements Query<OwnerAccount|null>
 */
final readonly class GetOwnerAccount implements Query {}
