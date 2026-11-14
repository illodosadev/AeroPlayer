<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The root URL: the dashboard for the signed-in owner, the login page for anyone else (app-foundation). It reads
 * the request's user rather than the auth() helper (single-action-controllers, design D3).
 */
class RedirectHomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect($request->user() !== null ? route('dashboard') : route('login'));
    }
}
