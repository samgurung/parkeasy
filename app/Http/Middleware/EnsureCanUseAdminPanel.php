<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the admin panel to the accounts that administer something.
 *
 * Not the security boundary - that is each admin component's own `Gate::authorize` calls,
 * which run per action and therefore also cover Livewire update requests, which do not pass
 * through route middleware at all. This settles the *page*: without it, an account with no
 * admin role passes `auth`, and the only thing that stops it is a 403 raised from inside the
 * component, having already rendered the shell of a page it cannot use.
 *
 * An operator is redirected to their kiosk page rather than refused, because running a gate
 * is the entire job and a 403 on a link the navigation never shows them tells them nothing.
 * Everyone else is refused exactly as before: an account that is neither staff nor an
 * operator has no business here, and redirecting it somewhere would only hide that.
 */
class EnsureCanUseAdminPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null, 401);

        if (! $user->canUseAdminPanel()) {
            abort_unless($user->operatesKiosks(), 403);

            return redirect()->route('home');
        }

        return $next($request);
    }
}
