<?php

namespace App\Http\Middleware;

use App\Models\Kiosk;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides which kiosk a browser is on, and remembers the choice for as long as it should
 * last.
 *
 * A kiosk is fixed hardware at a fixed gate, so the terminal should not have to be told its
 * own identity every time someone opens the browser. The `?kiosk=<key>` query string binds
 * the terminal, and the binding is then held in two places with two different lifetimes,
 * because two different questions are being asked:
 *
 *   - the session, which lasts as long as the visit or the login, and
 *   - a year-long cookie, which survives a browser restart, a crash, or a closed tab.
 *
 * Resolution order is query string, then session, then cookie - narrowest scope first. An
 * explicit `?kiosk=` in a link (or a QR code on the gate) always wins, so re-binding or
 * moving a terminal is just a new URL and nothing needs clearing first, because a new value
 * simply overwrites the old one.
 *
 * Why both stores, and why the session is read as well as written. An anonymous terminal has
 * no login, so the session is the only thing that ends when the browser restarts - the
 * cookie is what keeps the gate bound across that. A signed-in browser has the opposite
 * problem: the selection should last exactly as long as the login and no longer, and a login
 * is *narrower* than a browser profile, so a cookie would keep it pinned for a year after
 * sign-out and dump whoever used that machine onto a gate view. That is why the year-long
 * cookie is written only for a browser that is the gate: an anonymous terminal, or an
 * operator's tablet. Everyone else's selection lives in the session and ends at the logout.
 *
 * A staff member who signed in before this rule existed may still be carrying such a cookie,
 * so the middleware clears it on any request rather than merely declining to refresh it -
 * which un-pins those machines without disturbing the session selection they now have.
 *
 * An operator is held to their own lot. They are running a gate, not browsing one, so
 * another lot's kiosk is refused outright rather than quietly rendered unbound.
 */
class ResolveKioskBinding
{
    /**
     * How long a terminal stays bound. A kiosk outlives any browser profile, so this is
     * deliberately long - a year, refreshed on every kiosk page view. Losing the binding
     * costs a physical trip to the gate, so it should not expire on a schedule.
     */
    public const COOKIE_NAME = 'parkeasy_kiosk';

    public const COOKIE_LIFETIME_MINUTES = 525600;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $kiosk = $this->kioskFromQuery($request)
            ?? $this->kioskFromSession($request)
            ?? $this->kioskFromCookie($request);

        // An operator is at the gate rather than browsing one, so a kiosk outside their lot
        // is refused outright. Silently rendering it unbound would look like the terminal had
        // been un-bound by accident; a 403 says what actually happened.
        if ($kiosk && $user?->operatesKiosks()) {
            Gate::authorize('operate', $kiosk);
        }

        // Only a real kiosk is reported to the page. An unrecognised key is either a typo or
        // a kiosk that has since been deleted, and binding to it would strand the terminal
        // on a gate that does not exist.
        if ($kiosk) {
            $request->attributes->set('kiosk', $kiosk);

            // The session is written for everyone: it is the record of what this browser is
            // currently on, and for a staff member it is the whole of their binding.
            $request->session()->put('kiosk_key', $kiosk->key);

            // The year-long cookie is only for a browser that *is* the gate. Signed-in staff
            // are on their own machine, so they get the cookie cleared rather than written.
            if ($user && ! $user->operatesKiosks()) {
                self::forgetCookie();
            } else {
                $this->remember($kiosk->key);
            }
        }

        return $next($request);
    }

    /**
     * Drop the binding entirely: the manual escape hatch behind /kiosk/forget.
     *
     * The cookie is queued rather than returned so neither the page's own response nor the
     * middleware's re-plant is disturbed, and because it lands after the middleware a bare
     * /kiosk/forget cannot be re-bound by the very response meant to clear it. Shared with
     * the controller so the escape hatch and the automatic staff clear cannot drift apart.
     */
    public static function release(Request $request): void
    {
        self::forgetCookie();

        $request->session()->forget('kiosk_key');
    }

    /**
     * Clear the cookie without touching the session, so a staff member's current selection
     * survives on a browser that was bound to a gate before the role existed.
     */
    public static function forgetCookie(): void
    {
        // Same path and domain as remember() below. A forget cookie that does not match the
        // original's scope leaves the original in place, and the browser stays bound.
        Cookie::queue(Cookie::forget(self::COOKIE_NAME, '/', config('session.domain')));
    }

    protected function kioskFromQuery(Request $request): ?Kiosk
    {
        return $this->kioskForKey(trim((string) $request->query('kiosk', '')));
    }

    protected function kioskFromSession(Request $request): ?Kiosk
    {
        return $this->kioskForKey($request->session()->get('kiosk_key'));
    }

    protected function kioskFromCookie(Request $request): ?Kiosk
    {
        return $this->kioskForKey($request->cookie(self::COOKIE_NAME));
    }

    /**
     * The kiosk for a key, or null if there is no key or no such kiosk.
     *
     * One place, because all three sources have to agree on the second condition: a key that
     * resolves to nothing has to fall through to the next source rather than reporting an
     * unbound page, or a stale session entry could mask a working cookie.
     */
    protected function kioskForKey(mixed $key): ?Kiosk
    {
        if (! is_string($key) || $key === '') {
            return null;
        }

        return Kiosk::where('key', $key)->first();
    }

    /**
     * Refresh the cookie on each visit so an active terminal effectively never expires.
     * Queued rather than returned so the page's own response is not disturbed.
     */
    protected function remember(string $kioskKey): void
    {
        Cookie::queue(cookie(
            name: self::COOKIE_NAME,
            value: $kioskKey,
            minutes: self::COOKIE_LIFETIME_MINUTES,
            path: '/',
            domain: config('session.domain'),
            secure: config('session.secure'),
            httpOnly: true,
            sameSite: 'lax',
        ));
    }
}
