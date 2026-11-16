<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Inertia\Middleware\EncryptHistory;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            EncryptHistory::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // The browser player's endpoints answer JSON even before their controller runs (spotify-playback-api,
        // design D6): a missing session is a 401, an expired one fails the CSRF check first and is a 419. The search
        // results endpoint answers the same JSON 401 (spotify-search-api, design D4), and so do the library's "Load more"
        // endpoints (spotify-library-tracks, design D4) and the YouTube Music polling endpoint (youtube-music-connection,
        // design D7).
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->expectsJson() && $request->is('spotify/token', 'spotify/player/*', 'search/results', 'library/liked/songs', 'library/playlists/spotify/*/songs', 'youtube-music/connect/status', 'queue', 'queue/*')) {
                return response()->json(['error' => 'unauthenticated'], 401, ['Cache-Control' => 'no-store, private']);
            }
        });

        // Laravel turns a TokenMismatchException into an HttpException(419) before the render callbacks run.
        $exceptions->render(function (HttpException $exception, Request $request) {
            if ($exception->getStatusCode() === 419
                && $exception->getPrevious() instanceof TokenMismatchException
                && $request->expectsJson()
                && $request->is('spotify/player/*', 'queue', 'queue/*')) {
                return response()->json(['error' => 'session_expired'], 419, ['Cache-Control' => 'no-store, private']);
            }
        });
    })->create();
