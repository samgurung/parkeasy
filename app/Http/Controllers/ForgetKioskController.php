<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ResolveKioskBinding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Lets an operator release a browser from a kiosk it is bound to.
 *
 * The binding is deliberately long-lived, which means a tablet that was once used at a gate
 * keeps showing that gate — including on a shared admin machine that merely opened a kiosk
 * link once. Re-binding is easy (open the new gate's URL), but a browser that should be
 * generic again needs a way out, otherwise the only fix is clearing site data by hand.
 */
class ForgetKioskController
{
    public function __invoke(Request $request): RedirectResponse
    {
        Cookie::queue(Cookie::forget(ResolveKioskBinding::COOKIE_NAME));

        // Queue the forget *after* the middleware may have re-planted a cookie on this
        // request, so a bare /kiosk/forget with no query string cannot be re-bound by the
        // very response that is meant to clear it.
        $request->session()->forget('kiosk_key');

        return redirect()->route('home');
    }
}
