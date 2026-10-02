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

        $user = $request->user();

        // A signed-out browser has no landing of its own, and the unbound terminal is as good
        // a destination as any - it is where a guest lands now anyway.
        if (! $user) {
            return redirect()->route('home');
        }

        // An operator unbinds in order to *choose* - they are moving a terminal to another
        // gate, and the unbound page is where the picker is offered. Staff are unbinding in
        // order to get off the gate, and they are shown no picker, so sending them there would
        // land them on a warning with nowhere to go. They go to their own landing instead.

        // Note this deliberately is not landingUrl() for everyone: for an operator with a
        // single operable kiosk that returns /?kiosk=<key>, which would re-bind them one
        // redirect later and turn the button into a no-op.
        return redirect()->to(
            $user->operatesKiosks() ? route('home') : $user->landingUrl()
        );
    }
}
