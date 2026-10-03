<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveKioskBinding;
use App\Livewire\Auth\Login;
use App\Models\Access;
use App\Models\Kiosk;
use App\Models\ParkingLot;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithStaff;
use Tests\TestCase;

/**
 * The operator role: gate staff who can sign in, run a terminal in their own lot, and
 * reach nothing else.
 *
 * Three properties are worth separating, because they are enforced in three different
 * places and only hold together if all three are tested: the account may not open the admin
 * panel, may run its own lot's kiosks, and may not run anyone else's.
 */
class OperatorAccessTest extends TestCase
{
    use InteractsWithStaff;

    private ParkingLot $lotA;

    private ParkingLot $lotB;

    private Kiosk $entryA;

    private Kiosk $exitA;

    private Kiosk $entryB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpStaff();

        $this->lotA = $this->lot('Lot A');
        $this->lotB = $this->lot('Lot B');

        // Lot A has both an entry and an exit gate, Lot B only an entry - which is what makes
        // the "one gate needs no choice" and "two gates do" cases distinguishable.
        $this->entryA = $this->gate($this->lotA, Kiosk::TYPE_ENTRY, 'a-entry', 'Entry A');
        $this->exitA = $this->gate($this->lotA, Kiosk::TYPE_EXIT, 'a-exit', 'Exit A');
        $this->entryB = $this->gate($this->lotB, Kiosk::TYPE_ENTRY, 'b-entry', 'Entry B');
    }

    private function lot(string $name): ParkingLot
    {
        return ParkingLot::create([
            'name' => $name, 'lot_number' => ParkingLot::nextLotNumber(),
            'rate_two_wheeler' => 10, 'rate_four_wheeler' => 20,
        ]);
    }

    private function gate(ParkingLot $lot, string $type, string $key, string $name): Kiosk
    {
        return Kiosk::create([
            'name' => $name, 'key' => $key, 'type' => $type, 'parking_lot_id' => $lot->id,
        ]);
    }

    /** Sign in through the real component, so the redirect under test is the one used. */
    private function signIn(User $user): Testable
    {
        return Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors();
    }

    // ── The role itself ────────────────────────────────────────────────────────

    public function test_the_catalogue_grants_an_operator_exactly_one_permission(): void
    {
        // Stated rather than derived by subtraction from the lot-admin set: an operator is
        // not an admin with things taken away, so adding an admin permission later must not
        // silently hand it to every gate attendant.
        $this->assertSame([Access::OPERATE_KIOSKS], Access::operatorPermissions());
        $this->assertNotContains(Access::MANAGE_KIOSKS, Access::operatorPermissions());
        $this->assertNotContains(Access::MANAGE_LOTS, Access::operatorPermissions());
    }

    public function test_the_operator_permission_is_actually_created_and_granted(): void
    {
        // Through the real catalogue rather than a bare role, so this cannot pass against a
        // permission set the app would never actually hand out.
        $operator = $this->operator([$this->lotA]);

        $this->assertTrue($operator->can(Access::OPERATE_KIOSKS));
        $this->assertFalse($operator->can(Access::MANAGE_KIOSKS));
        $this->assertFalse($operator->can(Access::MANAGE_FLOORS));
        $this->assertFalse($operator->can(Access::MANAGE_LOTS));
        $this->assertFalse($operator->can(Access::UPDATE_VEHICLES));
    }

    public function test_an_operator_is_scoped_to_their_lots_like_any_other_staff(): void
    {
        // Lot scoping comes from the pivot, not from the role: administeredLotIds() only
        // exempts the super admin, so an operator inherits the boundary from existing code
        // rather than needing a scoping rule of its own.
        $operator = $this->operator([$this->lotA]);

        $this->assertTrue($operator->administersLot($this->lotA));
        $this->assertFalse($operator->administersLot($this->lotB));
        $this->assertSame([$this->lotA->id], $operator->administeredLotIds());
    }

    public function test_an_operator_with_no_lots_can_operate_nothing(): void
    {
        // Failing closed on an unassigned account is the point of an empty array.
        $operator = $this->operator();

        $this->assertFalse($operator->can('operate', $this->entryA));
        $this->assertTrue($operator->operableKiosks()->isEmpty());
    }

    public function test_a_super_admin_can_still_operate_any_kiosk_including_an_unlinked_one(): void
    {
        // The break-glass account has to stay able to preview a terminal nobody has claimed
        // yet, which is the one kiosk state the lot check cannot answer.
        $super = $this->superAdmin();
        $unlinked = Kiosk::create([
            'name' => 'Spare', 'key' => 'spare', 'type' => Kiosk::TYPE_ENTRY, 'parking_lot_id' => null,
        ]);

        $this->assertTrue($super->can('operate', $this->entryB));
        $this->assertTrue($super->can('operate', $unlinked));
    }

    // ── The admin panel is closed ──────────────────────────────────────────────

    public function test_an_operator_is_redirected_away_from_every_admin_page(): void
    {
        $this->actingAsOperator([$this->lotA]);

        // Redirected rather than refused: a 403 on a link the navigation never shows them
        // would tell an operator nothing. Where they should be is the gate.
        foreach (['admin.lots', 'admin.floors', 'admin.kiosks', 'admin.vehicles'] as $route) {
            $this->get(route($route))->assertRedirect(route('home'));
        }
    }

    public function test_an_operator_is_never_shown_the_setup_navigation(): void
    {
        $this->actingAsOperator([$this->lotA]);

        $nav = $this->blade('<x-site-nav />');

        foreach (['/admin/lots', '/admin/floors', '/admin/kiosks', '/admin/vehicles'] as $link) {
            $this->assertStringNotContainsString($link, $nav);
        }
    }

    public function test_an_operator_is_named_as_one_rather_than_as_a_lot_admin(): void
    {
        $this->actingAsOperator([$this->lotA]);

        // The nav badge falls through the roles in order, so without an operator branch an
        // operator would be described by the panel they cannot use.
        $this->assertStringContainsString('Operator', $this->blade('<x-site-nav />'));
    }

    public function test_staff_are_unaffected_by_the_panel_entry_point_changing(): void
    {
        // The route group gained a middleware and the lots policy swapped an inline role
        // check for canUseAdminPanel(), so the two roles allowed before must still be.
        $this->actingAsSuperAdmin();
        $this->get(route('admin.lots'))->assertOk();

        $this->actingAsLotAdmin([$this->lotA]);
        $this->get(route('admin.lots'))->assertOk();
    }

    // ── Running a gate ─────────────────────────────────────────────────────────

    public function test_an_operator_may_operate_their_own_lots_kiosks(): void
    {
        $operator = $this->operator([$this->lotA]);

        $this->assertTrue($operator->can('operate', $this->entryA));
        $this->assertTrue($operator->can('operate', $this->exitA));
    }

    public function test_an_operator_may_not_operate_another_lots_kiosk(): void
    {
        $this->assertFalse($this->operator([$this->lotA])->can('operate', $this->entryB));
    }

    public function test_an_operator_is_refused_a_foreign_kiosk_url_outright(): void
    {
        $this->actingAsOperator([$this->lotA]);

        // 403, not an unbound page. Rendering it silently would look like the terminal had
        // been un-bound by accident; a refusal says what actually happened.
        $this->get('/?kiosk='.$this->entryB->key)->assertForbidden();
    }

    public function test_an_operator_is_refused_a_delinked_gate(): void
    {
        $operator = $this->actingAsOperator([$this->lotA]);

        $spare = Kiosk::create([
            'name' => 'Spare', 'key' => 'spare', 'type' => Kiosk::TYPE_ENTRY, 'parking_lot_id' => null,
        ]);

        // A delinked kiosk has no lot, so the lot check has nothing to place it in. That is a
        // refusal and not a crash: the operator gets the same 403 as for a foreign lot rather
        // than a 500 from administering a lot that does not exist. The super admin is still
        // let in, since a broken terminal is exactly what they come to look at.
        $this->assertFalse($operator->can('operate', $spare));

        $this->get('/?kiosk=spare')->assertForbidden();

        $this->actingAsSuperAdmin();
        $this->get('/?kiosk=spare')->assertOk();
    }

    public function test_an_operator_gets_the_kiosk_page_for_their_own_gate(): void
    {
        $this->actingAsOperator([$this->lotA]);

        $this->get('/?kiosk='.$this->entryA->key)
            ->assertOk()
            ->assertSee('Entry A')
            ->assertSee('This gate');
    }

    public function test_an_operator_stays_bound_across_a_browser_restart(): void
    {
        $this->actingAsOperator([$this->lotA]);

        // The reason an operator signs in on a tablet at all: the binding has to outlive the
        // session, exactly as it does on an anonymous terminal. Staff, who are only ever
        // previewing a gate, are deliberately kept off the long-lived cookie.
        $this->get('/?kiosk='.$this->entryA->key)
            ->assertOk()
            ->assertCookie(ResolveKioskBinding::COOKIE_NAME, 'a-entry');

        $this->assertSame('a-entry', session('kiosk_key'));
    }

    public function test_an_operator_keeps_its_kiosk_after_navigating_away(): void
    {
        $this->actingAsOperator([$this->lotA]);

        $this->get('/?kiosk='.$this->entryA->key)->assertOk();

        // The URL only has to carry the selection once. Arriving through the nav, or just
        // refreshing, drops the query string - and an operator who is standing at a gate
        // should still be standing at it.
        $this->get('/')
            ->assertOk()
            ->assertSee('Entry A')
            ->assertSee('This gate');
    }

    public function test_staff_previewing_a_kiosk_still_hold_no_binding(): void
    {
        // The counterpart, so the operator exemption cannot quietly become a hole: reaching
        // a kiosk URL with an admin account must not persist the binding.
        $this->actingAsLotAdmin([$this->lotA]);

        $this->get('/?kiosk='.$this->entryA->key)
            ->assertOk()
            ->assertCookieExpired(ResolveKioskBinding::COOKIE_NAME);
    }

    // ── Picking a gate ─────────────────────────────────────────────────────────

    public function test_an_operator_with_two_gates_is_offered_a_choice(): void
    {
        $this->actingAsOperator([$this->lotA]);

        // Entry or exit is a real decision, so it is asked rather than guessed at - and
        // only their own lot's gates are on offer.
        $this->get('/')
            ->assertOk()
            ->assertSee('Choose your gate')
            ->assertSee('Entry A')
            ->assertSee('Exit A')
            ->assertDontSee('Entry B');
    }

    public function test_the_picker_is_replaced_by_the_scanner_once_a_gate_is_bound(): void
    {
        $this->actingAsOperator([$this->lotA]);

        // A greyed-out scanner under a "choose your gate" prompt would be contradictory.
        $this->get('/?kiosk='.$this->entryA->key)
            ->assertOk()
            ->assertDontSee('Choose your gate')
            ->assertSee('This gate');
    }

    public function test_an_operator_with_one_gate_is_sent_straight_to_it(): void
    {
        // A single-gate tablet should need no further taps after the login it already
        // performed, so the one kiosk becomes the landing URL rather than a choice.
        $this->assertSame(
            route('home', ['kiosk' => 'b-entry']),
            $this->operator([$this->lotB])->landingUrl(),
        );
    }

    public function test_an_operator_with_several_gates_lands_on_the_picker(): void
    {
        $this->assertSame(route('home'), $this->operator([$this->lotA])->landingUrl());
    }

    public function test_no_picker_is_offered_to_someone_who_cannot_operate(): void
    {
        // Staff previewing a kiosk have no gates to choose from - a list would be wrong for
        // them. It used to also be noise for anonymous visitors, but the terminal is behind a
        // login now, so there is no such thing as an anonymous visitor here.
        $this->actingAsSuperAdmin();
        $this->get('/')->assertOk()->assertDontSee('Choose your gate');
    }

    public function test_an_operator_gets_the_card_picker_and_not_the_staff_picker(): void
    {
        // The two controls are mutually exclusive by design, and neither appears once a gate
        // is bound. The card picker replaces the whole terminal because choosing a gate is an
        // operator's start-of-shift decision; the staff dropdown is a compact control above the
        // gate panes. An operator holding both would be able to answer the same question two
        // ways, and on an unbound terminal only the picker's answer reaches the scanner.
        $this->actingAsOperator([$this->lotA]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Choose your gate')
            ->assertDontSee('id="kiosk-picker"', false);

        // Bound, the picker gives way to the terminal - and an operator still gets no staff
        // dropdown, so the gate they are standing at is the gate they stay on.
        $this->get('/?kiosk='.$this->entryA->key)
            ->assertOk()
            ->assertSee('id="scan-entry"', false)
            ->assertDontSee('id="kiosk-picker"', false);
    }

    // ── Signing in ─────────────────────────────────────────────────────────────

    public function test_an_operator_is_landed_at_their_gate_after_signing_in(): void
    {
        $operator = $this->operator([$this->lotA], ['email' => 'op@parkeasy.test']);

        $this->signIn($operator)->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($operator);
    }

    public function test_an_operator_with_one_gate_is_landed_on_it_after_signing_in(): void
    {
        $this->signIn($this->operator([$this->lotB], ['email' => 'solo@parkeasy.test']))
            ->assertRedirect(route('home', ['kiosk' => 'b-entry']));
    }

    public function test_staff_are_still_landed_in_the_panel_after_signing_in(): void
    {
        $this->signIn($this->lotAdmin([$this->lotA], ['email' => 'la@parkeasy.test']))
            ->assertRedirect(route('admin.lots'));
    }

    public function test_an_operator_keeps_their_gate_across_a_sign_out_and_sign_in(): void
    {
        $operator = $this->operator([$this->lotB], ['email' => 'shift@parkeasy.test']);
        $this->actingAs($operator);

        $this->get(route('home', ['kiosk' => 'b-entry']))
            ->assertOk()
            ->assertCookie(ResolveKioskBinding::COOKIE_NAME, 'b-entry');

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();

        // The browser carried the binding through the sign-out, so the next shift does not
        // have to re-bind the tablet from a URL by hand. This is the behaviour an admin
        // account deliberately does not get, and the reason the two roles are told apart.
        $this->withCookie(ResolveKioskBinding::COOKIE_NAME, 'b-entry');

        $this->signIn($operator->fresh())
            ->assertRedirect(route('home', ['kiosk' => 'b-entry']));

        $this->get('/')->assertOk()->assertSee('Entry B');
    }

    // ── Whose gate is it? ──────────────────────────────────────────────────────

    public function test_a_gate_bound_by_one_shift_is_not_handed_to_the_next(): void
    {
        // The tablet is fixed hardware at a fixed gate, but the account signed in on it is
        // not: the next shift belongs to another lot and must start at their own gate rather
        // than standing at a stranger's barrier with a working scanner.
        $first = $this->operator([$this->lotA], ['email' => 'first@parkeasy.test']);

        $this->actingAs($first);
        $this->get(route('home', ['kiosk' => 'a-entry']))->assertOk();

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();

        // Signed out, the terminal still carries the binding - that is the whole point of the
        // year-long cookie - so it is handed on to whoever signs in next. The account it was
        // bound by travels with it.
        $this->withCookie(ResolveKioskBinding::COOKIE_NAME, 'a-entry')
            ->withCookie(ResolveKioskBinding::OWNER_COOKIE_NAME, (string) $first->id)
            ->get(route('login'))
            ->assertOk();

        $this->assertSame('a-entry', session('kiosk_key'));

        $this->signIn($this->operator([$this->lotB], ['email' => 'second@parkeasy.test']));

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Entry A')
            ->assertDontSee('This gate');

        // Dropped outright, rather than left in the session to be re-checked on every later
        // request: the binding was never this account's to keep.
        $this->assertNull(session('kiosk_key'));
    }

    public function test_a_lot_admin_is_not_handed_another_lots_gate_on_the_same_browser(): void
    {
        // The same leak seen from the admin side, and the quieter one: staff are never
        // authorised against a kiosk, so nothing refused them a foreign gate. The admin would
        // simply be looking at another lot's terminal, scans and all, with no picker on screen
        // to tell them so - because a bound terminal hides it.
        $first = $this->operator([$this->lotA], ['email' => 'first@parkeasy.test']);

        $this->actingAs($first);
        $this->get(route('home', ['kiosk' => 'a-entry']))->assertOk();

        $this->post(route('logout'));

        $this->withCookie(ResolveKioskBinding::COOKIE_NAME, 'a-entry')
            ->withCookie(ResolveKioskBinding::OWNER_COOKIE_NAME, (string) $first->id)
            ->get('/')
            ->assertOk();

        $this->signIn($this->lotAdmin([$this->lotB], ['email' => 'la@parkeasy.test']));

        // The terminal is where an admin lands to work, and unbound it offers the gates of
        // their own lot.
        $this->get('/')
            ->assertOk()
            ->assertDontSee('Entry A')
            ->assertDontSee('This gate')
            ->assertSee('Entry B');

        $this->assertNull(session('kiosk_key'));
    }

    public function test_a_gate_binding_survives_a_handover_between_operators_of_the_same_lot(): void
    {
        // The counterpart, so the ownership check cannot quietly become a re-bind on every
        // shift change. The gate is the tablet's, not the account's: whoever signs in at a
        // terminal already standing on that gate keeps it, and the binding is re-stamped for
        // them. Only a gate they could never run is taken away.
        $first = $this->operator([$this->lotA], ['email' => 'first@parkeasy.test']);

        $this->actingAs($first);
        $this->get(route('home', ['kiosk' => 'a-entry']))->assertOk();

        $this->post(route('logout'));

        $this->withCookie(ResolveKioskBinding::COOKIE_NAME, 'a-entry')
            ->withCookie(ResolveKioskBinding::OWNER_COOKIE_NAME, (string) $first->id)
            ->get(route('login'))
            ->assertOk();

        $second = $this->operator([$this->lotA], ['email' => 'second@parkeasy.test']);
        $this->signIn($second);

        $this->get('/')
            ->assertOk()
            ->assertSee('Entry A')
            ->assertSee('This gate');

        $this->assertSame('a-entry', session('kiosk_key'));

        // Re-stamped, so the binding now belongs to the account actually signed in.
        $this->get('/')
            ->assertCookie(ResolveKioskBinding::OWNER_COOKIE_NAME, (string) $second->id);
    }

    public function test_a_binding_made_before_it_was_owned_is_still_honoured(): void
    {
        // A gate tablet already carrying a kiosk cookie from before the binding was stamped
        // with an account - and a first-time visitor who followed a QR code to a signed-out
        // terminal, which is deliberately left unowned until somebody signs in. An absent
        // owner means "not claimed yet", never "refused".
        $lot = $this->lotA;
        $this->actingAs($this->operator([$lot]));

        $this->withCookie(ResolveKioskBinding::COOKIE_NAME, 'a-entry')
            ->get('/')
            ->assertOk()
            ->assertSee('Entry A')
            ->assertSee('This gate');
    }
}
