<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Every Aero component and state on one page (aero-design).
 */
class ShowStyleguideController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Styleguide');
    }
}
