<?php

namespace App\Http\Middleware;

use App\Models\Kiosk;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers which kiosk a browser is bound to.
 *
 * A kiosk is a fixed piece of hardware at a fixed gate, so the terminal should not have to
 * be told its own identity every time someone opens the browser. The `?kiosk=<key>` query
 * string binds the terminal, and that binding is mirrored into two places:
 *
 *   - the session, for the current visit, and
 *   - a long-lived cookie, so it survives a browser restart, a crash, or a closed tab.
 *
 * The session alone is not enough. A kiosk sits idle between shifts and browsers discard
 * session state on restart or when site data is cleared, which would drop the terminal back
 * to the un-bound state and make every scan fail until someone re-entered the URL.
 *
 * Resolution order is query string, then cookie: an explicit `?kiosk=` in a link (or a QR
 * code on the gate) always wins, so re-binding or moving a terminal is just a new URL.
 * Nothing needs clearing, because the new value simply overwrites the old one.
 *
 * The session is written alongside the cookie so the current visit has a record of which
 * terminal it is on, and so unbinding has something to clear. It is deliberately not a
 * resolution source: reading it would reintroduce the exact fragility the cookie exists to
 * avoid, and the cookie is strictly the more durable of the two.
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
        $kiosk = $this->kioskFromQuery($request) ?? $this->kioskFromCookie($request);

        // Only a real kiosk is reported to the page. An unrecognised key in the URL is
        // either a typo or a kiosk that has since been deleted, and binding to it would
        // strand the terminal on a gate that does not exist.
        if ($kiosk) {
            $request->attributes->set('kiosk', $kiosk);
            $request->session()->put('kiosk_key', $kiosk->key);

            $this->remember($kiosk->key);
        }

        return $next($request);
    }

    protected function kioskFromQuery(Request $request): ?Kiosk
    {
        $key = trim((string) $request->query('kiosk', ''));

        return $key === '' ? null : Kiosk::where('key', $key)->first();
    }

    protected function kioskFromCookie(Request $request): ?Kiosk
    {
        $key = $request->cookie(self::COOKIE_NAME);

        return is_string($key) && $key !== '' ? Kiosk::where('key', $key)->first() : null;
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
