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
 */
class ForgetKioskController
{
    public function __invoke(Request $request): RedirectResponse
    {
        // Queued *after* the middleware may have re-planted a cookie on this request, so a
        // bare /kiosk/forget with no query string cannot be re-bound by the very response
        // that is meant to clear it.
        ResolveKioskBinding::release($request);

        // Staff and operators are sent to different pages, because "unbind" means different
        // things to them and the two pages are already distinct.
        //
        // An operator unbinds in order to *choose* which gate to run next, so they land on
        // the unbound terminal, where the picker is offered. Staff are managing kiosks, so
        // they land on the admin kiosk list - the page they navigate between gates from, and
        // the one that answers "which kiosk is this?" with a list rather than a gate picker
        // that is not theirs to use.
        //
        // Neither may be landingUrl(). For an operator with a single operable kiosk that
        // returns /?kiosk=<key>, which would re-bind them one redirect later and turn the
        // button into a silent no-op.
        if ($request->user()?->canUseAdminPanel()) {
            return redirect()->route('admin.kiosks');
        }

        return redirect()->route('home');
    }
}
