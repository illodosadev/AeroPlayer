<?php

namespace App\Http\Controllers\Auth;

use App\Application\Auth\Commands\LogInOwner;
use App\Application\Auth\Exceptions\InvalidCredentials;
use App\Application\Auth\Exceptions\TooManyLoginAttempts;
use App\Bus\CommandBus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Handle an incoming authentication request: LogInOwner, then a new session id.
 */
class LogInController extends Controller
{
    public function __construct(private readonly CommandBus $commands) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(LoginRequest $request): RedirectResponse
    {
        try {
            $this->commands->dispatch(new LogInOwner(
                (string) $request->input('email'),
                (string) $request->input('password'),
                $request->boolean('remember'),
                (string) $request->ip(),
            ));
        } catch (TooManyLoginAttempts $failure) {
            event(new Lockout($request));

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $failure->seconds,
                    'minutes' => ceil($failure->seconds / 60),
                ]),
            ]);
        } catch (InvalidCredentials) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
