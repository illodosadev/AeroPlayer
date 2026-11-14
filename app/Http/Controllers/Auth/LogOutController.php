<?php

namespace App\Http\Controllers\Auth;

use App\Application\Auth\Commands\LogOutOwner;
use App\Bus\CommandBus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Destroy an authenticated session: LogOutOwner, then end the session and its browser history.
 */
class LogOutController extends Controller
{
    public function __construct(private readonly CommandBus $commands) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $this->commands->dispatch(new LogOutOwner);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Must run after invalidate(): the session flush would drop this flag.
        Inertia::clearHistory();

        return redirect(route('login'));
    }
}
