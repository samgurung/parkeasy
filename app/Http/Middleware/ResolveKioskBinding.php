<?php

namespace App\Http\Middleware;

use App\Models\Kiosk;
use App\Models\User;
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
 * Why both stores, and why the session is read as well as written. An operator's tablet is
 * the gate, and a tablet is switched off and rebooted mid-shift, so the session alone is not
 * enough - the year-long cookie is what keeps the gate bound across a restart, and it is
 * paired with a remember-me login for the same reason: losing either one costs a walk to the
 * barrier. A signed-in staff member has the opposite problem: their selection should last
 * exactly as long as the login and no longer, and a login is *narrower* than a browser
 * profile, so a cookie would keep it pinned for a year after sign-out and dump whoever used
 * that machine onto a gate view. That is why the year-long cookie is written only for a
 * browser that *is* the gate - an operator's tablet - and cleared for everyone else, whose
 * selection lives in the session and ends at the logout.
 *
 * This middleware runs before the `auth` middleware, so a signed-out browser arriving with a
 * `?kiosk=` link has its binding resolved and remembered, and is then redirected to sign in.
 * That is deliberate: it is what puts the operator back at the gate they just scanned a QR
 * code for, instead of on a generic landing page.
 *
 * A staff member who signed in before this rule existed may still be carrying such a cookie,
 * so the middleware clears it on any request rather than merely declining to refresh it -
 * which un-pins those machines without disturbing the session selection they now have.
 *
 * An operator is held to their own lot. They are running a gate, not browsing one, so
 * another lot's kiosk is refused outright rather than quietly rendered unbound.
 *
 * Both stores also record *which account* made the binding, because a lifetime and an owner
 * are different questions and the two stores answer only the first. A gate tablet outlives
 * the login - that is what the year-long cookie is for - but it also outlives the account, and
 * the next person to sign in on it may be at a different lot: two shifts sharing one tablet
 * would otherwise leave the second standing at the first one's barrier with a working
 * scanner, posting scans and reading recent entries for a lot they have no claim to. An
 * inherited binding is therefore dropped rather than adopted, and the terminal falls back to
 * offering this account its own gates.
 *
 * The owner is a stamp, never the authority. Whoever signs in at a terminal already standing
 * on a gate they are entitled to run keeps the binding and has it re-stamped for them - the
 * gate belongs to the tablet, not to the account, so a handover between two operators of one
 * lot must not cost a re-bind - and an absent owner means "not claimed yet", which is the
 * state a signed-out browser is in after following a printed link.
 */
class ResolveKioskBinding
{
    /**
     * How long a terminal stays bound. A kiosk outlives any browser profile, so this is
     * deliberately long - a year, refreshed on every kiosk page view. Losing the binding
     * costs a physical trip to the gate, so it should not expire on a schedule.
     */
    public const COOKIE_NAME = 'parkeasy_kiosk';

    /**
     * The account the binding was made by, alongside the gate key. A separate cookie rather
     * than a compound value, so the key stays readable on its own terms and a tablet bound
     * before this rule existed resolves either way.
     */
    public const OWNER_COOKIE_NAME = 'parkeasy_kiosk_owner';

    public const COOKIE_LIFETIME_MINUTES = 525600;

    /** The session half of the pair, kept in step with the two cookies above. */
    private const SESSION_KEY = 'kiosk_key';

    private const SESSION_OWNER_KEY = 'kiosk_owner';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // An explicit ?kiosk= is a decision rather than a leftover, so it carries no owner and
        // is adopted outright - including by an account the kiosk was never bound to before.
        $requested = $this->kioskFromQuery($request);
        $kiosk = $requested;
        $owner = null;

        if (! $kiosk) {
            [$kiosk, $owner] = $this->remembered($request);
        }

        // A binding made by somebody else is not this browser's to keep. Released outright
        // rather than merely ignored, or it would be re-resolved and re-judged on every later
        // request, and the terminal would have no way back to its unbound state.
        if ($kiosk && $this->isForeignTo($user, $owner, $kiosk)) {
            self::release($request);

            $kiosk = null;
            $owner = null;
        }

        // An operator is at the gate rather than browsing one, so a kiosk outside their lot
        // is refused outright. Silently rendering it unbound would look like the terminal had
        // been un-bound by accident; a 403 says what actually happened. Only the URL can
        // produce this - a binding inherited from whoever had the tablet last is dropped
        // above, because nobody asked for it.
        if ($requested && $user?->operatesKiosks()) {
            Gate::authorize('operate', $requested);
        }

        // Only a real kiosk is reported to the page. An unrecognised key is either a typo or
        // a kiosk that has since been deleted, and binding to it would strand the terminal
        // on a gate that does not exist.
        if ($kiosk) {
            $request->attributes->set('kiosk', $kiosk);

            // The session is written for everyone: it is the record of what this browser is
            // currently on, and for a staff member it is the whole of their binding.
            $request->session()->put(self::SESSION_KEY, $kiosk->key);

            // Stamped with whoever is signed in, or carried through as it stands while signed
            // out - which is what lets this binding put the operator back at the gate after
            // the login, and lets the account they sign in as be the one it is then held for.
            $request->session()->put(self::SESSION_OWNER_KEY, $user?->getAuthIdentifier() ?? $owner);

            // The year-long cookie belongs to the gate tablet and nothing else. Staff are on
            // their own machine, so their selection lives in the session and they get the
            // cookie cleared rather than written. A guest gets neither: the terminal is
            // behind a login, so there is no anonymous gate left to keep bound, and this
            // middleware runs on the public dashboards too - planting a durable gate cookie
            // from /lots would outlive whatever the browser did next.
            if ($user?->operatesKiosks()) {
                $this->remember($kiosk->key, $user->getAuthIdentifier());
            } else {
                self::forgetCookie();
            }
        }

        return $next($request);
    }

    /**
     * Is this binding somebody else's, so that this account must not inherit it?
     *
     * A signed-out browser is never refused: it cannot be told what it is not entitled to
     * until somebody signs in, and that request is the next one through here. An ownerless
     * binding is likewise nobody's claim - a link followed, or a tablet from before the stamp
     * existed - so it stands until an account contradicts it.
     */
    protected function isForeignTo(?User $user, ?int $owner, Kiosk $kiosk): bool
    {
        if ($user === null || $owner === null) {
            return false;
        }

        if ($owner === (int) $user->getAuthIdentifier()) {
            return false;
        }

        // Same gate, new shift: keep it. Only a gate this account could never stand at is
        // taken away, so the lot check below is the one answering what the owner stamp raises,
        // rather than a second rule written here.
        return ! $user->can('operate', $kiosk) && ! $user->can('view', $kiosk);
    }

    /**
     * Drop the binding entirely: the manual escape hatch behind /kiosk/forget, and the answer
     * to a binding inherited from an account this one has nothing to do with.
     *
     * The cookies are queued rather than returned so neither the page's own response nor the
     * middleware's re-plant is disturbed, and because it lands after the middleware a bare
     * /kiosk/forget cannot be re-bound by the very response meant to clear it. Shared with
     * the controller so the escape hatch and the automatic clear cannot drift apart.
     */
    public static function release(Request $request): void
    {
        self::forgetCookie();

        $request->session()->forget(self::SESSION_KEY);
        $request->session()->forget(self::SESSION_OWNER_KEY);
    }

    /**
     * Clear the cookies without touching the session, so a staff member's current selection
     * survives on a browser that was bound to a gate before the role existed.
     */
    public static function forgetCookie(): void
    {
        // Same path and domain as remember() below. A forget cookie that does not match the
        // original's scope leaves the original in place, and the browser stays bound.
        foreach ([self::COOKIE_NAME, self::OWNER_COOKIE_NAME] as $name) {
            Cookie::queue(Cookie::forget($name, '/', config('session.domain')));
        }
    }

    protected function kioskFromQuery(Request $request): ?Kiosk
    {
        return $this->kioskForKey(trim((string) $request->query('kiosk', '')));
    }

    /**
     * The binding held in one of the two remembered stores: the kiosk, and the account that
     * bound it.
     *
     * Read as a pair from a single store rather than resolved independently, because a session
     * naming one gate and a cookie naming another would otherwise hand a browser a binding
     * belonging to neither. The fall-through is unchanged: a session entry whose kiosk has
     * since been deleted must not mask a working cookie.
     *
     * @return array{0: ?Kiosk, 1: ?int}
     */
    protected function remembered(Request $request): array
    {
        $fromSession = $this->kioskForKey($request->session()->get(self::SESSION_KEY));

        if ($fromSession) {
            return [$fromSession, $this->ownerId($request->session()->get(self::SESSION_OWNER_KEY))];
        }

        return [
            $this->kioskForKey($request->cookie(self::COOKIE_NAME)),
            $this->ownerId($request->cookie(self::OWNER_COOKIE_NAME)),
        ];
    }

    /**
     * The kiosk for a key, or null if there is no key or no such kiosk.
     *
     * One place, because all the sources have to agree on the second condition: a key that
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
     * An account id from a session value or a cookie string, or null when there is none.
     *
     * Null rather than zero for anything unrecognisable, because "bound by nobody" is a real
     * state - a link followed while signed out - and must not be read as an account.
     */
    protected function ownerId(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Refresh the cookies on each visit so an active terminal effectively never expires.
     * Queued rather than returned so the page's own response is not disturbed.
     */
    protected function remember(string $kioskKey, int|string $owner): void
    {
        $values = [self::COOKIE_NAME => $kioskKey, self::OWNER_COOKIE_NAME => (string) $owner];

        foreach ($values as $name => $value) {
            Cookie::queue(cookie(
                name: $name,
                value: $value,
                minutes: self::COOKIE_LIFETIME_MINUTES,
                path: '/',
                domain: config('session.domain'),
                secure: config('session.secure'),
                httpOnly: true,
                sameSite: 'lax',
            ));
        }
    }
}
