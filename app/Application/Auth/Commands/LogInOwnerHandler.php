<?php

namespace App\Application\Auth\Commands;

use App\Application\Auth\Exceptions\InvalidCredentials;
use App\Application\Auth\Exceptions\TooManyLoginAttempts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final readonly class LogInOwnerHandler
{
    /**
     * Failed attempts allowed per email and ip before the login locks (owner-auth, "Login attempts are rate limited").
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * @throws TooManyLoginAttempts
     * @throws InvalidCredentials
     */
    public function handle(LogInOwner $command): void
    {
        $key = Str::transliterate(Str::lower($command->email).'|'.$command->ip);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw new TooManyLoginAttempts(RateLimiter::availableIn($key));
        }

        if (! Auth::attempt(['email' => $command->email, 'password' => $command->password], $command->remember)) {
            RateLimiter::hit($key);

            throw new InvalidCredentials;
        }

        RateLimiter::clear($key);
    }
}
