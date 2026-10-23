<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "/" on any host: the platform host goes to Super Admin sign-in, every other
 * host to the school dashboard (which shows the neutral page for unknown hosts).
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if (strtolower($request->getHost()) === strtolower((string) config('shiksha.platform_host'))) {
            return redirect()->route('platform.sign-in');
        }

        return redirect()->route('dashboard');
    }
}
