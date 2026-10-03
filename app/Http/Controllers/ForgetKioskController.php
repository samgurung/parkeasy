<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ResolveKioskBinding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Releases a browser from the kiosk it is bound to.
 *
 * The binding is deliberately long-lived, so a tablet that was once used at a gate keeps
 * showing that gate. Re-binding is easy (open the new gate's URL), but a browser that should
 * be generic again needs a way out, otherwise the only fix is clearing site data by hand.
 *
 * This is the manual escape hatch, offered to any account bound to a kiosk rather than only
 * to operators. It is a public route and mutates nothing but the caller's own binding, so it
 * was never a boundary - and gating it on the role meant the person most likely to be
 * stranded on a gate (a lot admin covering a shift) was the one shown no way off.
 *
 * Always returns to the terminal rather than to a page chosen by role, because the point of
 * unbinding is to choose a kiosk somewhere else, and the terminal is where that choice is
 * made. `/admin/kiosks` was the previous answer for staff and made this a detour.
 */
class ForgetKioskController
{
    public function __invoke(Request $request): RedirectResponse
    {
        // Queued *after* the middleware may have re-planted a cookie on this request, so a
        // bare /kiosk/forget with no query string cannot be re-bound by the very response
        // that is meant to clear it.
        ResolveKioskBinding::release($request);

        // Back to the terminal, for everyone. Unbinding is not "go and manage kiosks", it is
        // "stop showing me this gate" - so the answer is the page you were just looking at,
        // unbound, which now offers whatever way this account picks a kiosk: the operator's
        // card picker or a staff member's dropdown.
        //
        // Sending staff to the admin kiosk list instead was the one destination that made
        // this a dead end: it dropped you out of the terminal you were unbinding to reach a
        // page that names kiosks but cannot show you a gate, and getting back meant coming
        // here again. Staying put keeps the two actions adjacent - unbind, then pick.
        //
        // Never landingUrl(). For an operator with a single operable kiosk that returns
        // /?kiosk=<key>, which would re-bind them one redirect later and turn the button into
        // a silent no-op.
        return redirect()->route('home');
    }
}
