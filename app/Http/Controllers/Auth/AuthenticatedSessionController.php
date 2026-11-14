<?php

namespace App\Http\Controllers\Auth;

use App\Application\Auth\Commands\LogInOwner;
use App\Application\Auth\Commands\LogOutOwner;
use App\Application\Auth\Exceptions\InvalidCredentials;
use App\Application\Auth\Exceptions\TooManyLoginAttempts;
use App\Bus\CommandBus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly CommandBus $commands) {}

    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     *
     * @throws ValidationException
     */
    public function store(LoginRequest $request): RedirectResponse
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

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $this->commands->dispatch(new LogOutOwner);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Must run after invalidate(): the session flush would drop this flag.
        Inertia::clearHistory();

        return redirect(route('login'));
    }
}
