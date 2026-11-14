<?php

namespace App\Application\Auth\Commands;

use Illuminate\Support\Facades\Auth;

final readonly class LogOutOwnerHandler
{
    public function handle(LogOutOwner $command): void
    {
        Auth::guard('web')->logout();
    }
}
