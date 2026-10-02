<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveKioskBinding;
use App\Models\Entry;
use App\Models\Kiosk;
use App\Models\ParkingLot;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RfidScanApiTest extends TestCase
{
    use RefreshDatabase;

    private const LOT_A = 501;

    private const LOT_B = 602;

    private function makeLot(int $lotNumber = self::LOT_A): ParkingLot
    {
        return ParkingLot::create([
            'name' => "Lot {$lotNumber}",
            'lot_number' => $lotNumber,
        ]);
    }

    private function makeVehicle(
        string $rfid = 'CARD-A',
        string $vehicleNumber = 'KA01AB1234',
        ?string $vehicleType = 'four_wheeler',
        string $driverName = 'Owner',
        string $mobile = '1234567890',
    ): Vehicle {
        return Vehicle::create([
            'rfid_id' => $rfid,
            'vehicle_number' => $vehicleNumber,
            'vehicle_type' => $vehicleType,
            'driver_name' => $driverName,
            'mobile_number' => $mobile,
        ]);
    }

    private function parkVehicle(Vehicle $vehicle, ParkingLot $lot): Entry
    {
        return Entry::create([
            'rfid_id' => $vehicle->rfid_id,
            'vehicle_id' => $vehicle->id,
            'entry_time' => now(),
            'status' => 'parked',
            'parking_lot_id' => $lot->id,
        ]);
    }

    /**
     * A gate of the requested type registered against the given lot. Scans are always made
     * from a real kiosk, because the kiosk's own type is what decides entry vs exit - there
     * is no direction in the request body.
     */
    private function makeKiosk(ParkingLot $lot, string $type, string $key = 'gate-a', ?string $name = null): Kiosk
    {
        return Kiosk::create([
            'name' => $name ?? ucfirst($type).' Gate',
            'key' => $key,
            'type' => $type,
            'parking_lot_id' => $lot->id,
        ]);
    }

    /**
     * Just an authenticated user. These tests are about who the binding belongs to, not
     * about what a given role may see, so no permission is granted on purpose - an
     * account with none is still a signed-in browser.
     */
    private function makeUser(): User
    {
        static $sequence = 0;
        $sequence++;

        return User::create([
            'name' => 'Staff',
            'email' => "staff{$sequence}@parkeasy.test",
            'password' => 'password',
        ]);
    }

    private function scan(string $rfid, string $type, int $lot): TestResponse
    {
        // A scan against a lot that does not exist 404s before the kiosk is ever looked
        // up, so there is no gate to create in that case.
        $kioskKey = ParkingLot::where('lot_number', $lot)->exists()
            ? $this->gateKeyFor($lot, $type)
            : 'ghost-gate';

        return $this->postJson('/api/rfid-scan', [
            'rfid_id' => $rfid,
            'lot' => $lot,
            'kiosk' => $kioskKey,
        ]);
    }

    /** Reuse a lot's existing gate if the test already made one, so keys stay unique. */
    private function gateKeyFor(int $lotNumber, string $type): string
    {
        $lot = ParkingLot::where('lot_number', $lotNumber)->firstOrFail();

        return Kiosk::firstOrCreate(
            ['parking_lot_id' => $lot->id, 'type' => $type],
            ['name' => ucfirst($type).' Gate', 'key' => $type.'-gate-'.$lotNumber],
        )->key;
    }

    private function enrol(
        string $rfid,
        string $vehicleNumber,
        string $vehicleType,
        string $driverName,
        int $lot = self::LOT_A,
    ): TestResponse {
        return $this->postJson('/api/rfid-scan/enrol', [
            'rfid_id' => $rfid,
            'driver_name' => $driverName,
            'vehicle_number' => $vehicleNumber,
            'vehicle_type' => $vehicleType,
            'mobile_number' => '9876543210',
            'lot' => $lot,
            'kiosk' => $this->gateKeyFor($lot, Kiosk::TYPE_ENTRY),
        ]);
    }

    public function test_lot_is_required_on_scan(): void
    {
        $this->makeLot();

        $this->postJson('/api/rfid-scan', ['rfid_id' => 'X', 'kiosk' => 'gate-a'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lot');
    }

    public function test_scan_with_unknown_lot_returns_404(): void
    {
        $this->makeLot();

        $this->scan('X', 'entry', 9999)
            ->assertNotFound()
            ->assertJson(['error' => 'Lot not found. Configure it in the admin panel first.']);
    }

    public function test_entry_scan_of_an_unregistered_card_asks_to_enrol_instead_of_failing(): void
    {
        $this->makeLot(self::LOT_A);

        // An unseen card is a first visit, not a failure: the kiosk collects the vehicle once.
        $this->scan('CARD-A', 'entry', self::LOT_A)
            ->assertOk()
            ->assertJson(['status' => 'enrolment_required', 'rfid_id' => 'CARD-A']);

        $this->assertDatabaseCount('vehicles', 0);
        $this->assertDatabaseCount('entries', 0);
    }

    public function test_enrolment_records_the_lot_the_card_was_bound_at(): void
    {
        // The kiosk already knows which lot it serves, so the origin of the registration is
        // free to capture. It is provenance only: the card stays valid at every other lot.
        $this->makeLot(self::LOT_A);
        $this->makeLot(self::LOT_B);

        $this->enrol('CARD-A', 'KA01AB1234', 'four_wheeler', 'John')
            ->assertOk()
            ->assertJson(['status' => 'parked', 'enrolled' => true]);

        $this->assertDatabaseHas('vehicles', [
            'rfid_id' => 'CARD-A',
            'registered_at_lot_id' => ParkingLot::where('lot_number', self::LOT_A)->value('id'),
        ]);
    }

    public function test_a_card_bound_at_one_lot_is_still_recognised_at_another(): void
    {
        // The point of keeping the registry global: provenance must not partition it.
        $this->makeLot(self::LOT_A);
        $this->makeLot(self::LOT_B);

        $this->enrol('CARD-A', 'KA01AB1234', 'four_wheeler', 'John')->assertOk();

        $this->scan('CARD-A', 'exit', self::LOT_A)->assertOk();
        // Resolved from the card alone at a lot it was never enrolled at.
        $this->scan('CARD-A', 'entry', self::LOT_B)
            ->assertOk()
            ->assertJson(['status' => 'parked']);

        // Still one vehicle, still bound to the lot it was enrolled at.
        $this->assertDatabaseCount('vehicles', 1);
        $this->assertDatabaseHas('vehicles', [
            'rfid_id' => 'CARD-A',
            'registered_at_lot_id' => ParkingLot::where('lot_number', self::LOT_A)->value('id'),
        ]);
    }

    public function test_a_card_is_only_ever_asked_for_its_vehicle_once(): void
    {
        $this->makeLot(self::LOT_A);

        $this->enrol('CARD-A', 'KA01AB1234', 'four_wheeler', 'John')
            ->assertOk()
            ->assertJson(['status' => 'parked', 'enrolled' => true]);

        // Every later visit is resolved from the card alone.
        $this->scan('CARD-A', 'exit', self::LOT_A)->assertOk();
        $this->scan('CARD-A', 'entry', self::LOT_A)->assertOk()->assertJson(['status' => 'parked']);

        $this->assertDatabaseCount('vehicles', 1);
        $this->assertDatabaseCount('entries', 2);
    }

    public function test_enrolment_binds_the_card_and_parks_the_vehicle_in_one_step(): void
    {
        $lot = $this->makeLot(self::LOT_A);

        $this->enrol('CARD-A', 'KA01AB1234', 'four_wheeler', 'John')
            ->assertOk()
            ->assertJson(['status' => 'parked', 'vehicle_number' => 'KA01AB1234', 'driver_name' => 'John']);

        $this->assertDatabaseHas('vehicles', [
            'rfid_id' => 'CARD-A',
            'vehicle_number' => 'KA01AB1234',
            'vehicle_type' => 'four_wheeler',
            'driver_name' => 'John',
            'mobile_number' => '9876543210',
        ]);

        $this->assertDatabaseHas('entries', [
            'rfid_id' => 'CARD-A',
            'vehicle_id' => Vehicle::where('rfid_id', 'CARD-A')->value('id'),
            'status' => 'parked',
            'parking_lot_id' => $lot->id,
        ]);
    }

    public function test_enrolment_validates_the_vehicle_details(): void
    {
        $lot = $this->makeLot(self::LOT_A);

        $this->postJson('/api/rfid-scan/enrol', [
            'rfid_id' => 'CARD-A',
            'driver_name' => '',
            'mobile_number' => '12345',
            'vehicle_number' => '',
            'vehicle_type' => 'spaceship',
            'lot' => self::LOT_A,
            'kiosk' => $this->makeKiosk($lot, Kiosk::TYPE_ENTRY)->key,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['driver_name', 'mobile_number', 'vehicle_number', 'vehicle_type']);

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_an_unregistered_card_cannot_be_enrolled_on_exit(): void
    {
        $this->makeLot(self::LOT_A);

        $this->enrol('CARD-A', 'KA01AB1234', 'four_wheeler', 'John')
            ->assertOk()->assertJson(['status' => 'parked']);

        // Enrolment is an entry-only step; leaving uses the normal exit path.
        $this->scan('CARD-B', 'exit', self::LOT_A)
            ->assertUnprocessable()
            ->assertJson(['error' => 'No active entry for this card']);
    }

    public function test_card_codes_are_matched_regardless_of_case(): void
    {
        $this->makeLot(self::LOT_A);
        $this->makeVehicle('CARD-A', 'KA01AB1234', 'four_wheeler', 'John', '9876543210');

        // The ESP32 reader posts lowercase; a case-sensitive lookup would treat the card
        // as unregistered and ask for the vehicle again.
        $this->scan('card-a', 'entry', self::LOT_A)
            ->assertOk()
            ->assertJson(['status' => 'parked', 'vehicle_number' => 'KA01AB1234']);
    }

    public function test_enrolment_does_not_rebind_a_card_that_is_already_registered(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $vehicle = $this->makeVehicle('CARD-A', 'KA01AB1234', 'four_wheeler', 'John', '9876543210');
        $this->parkVehicle($vehicle, $lot);

        // A second kiosk opened its enrolment form before this card was enrolled, and only
        // now submits it. The card must keep its real owner rather than being re-keyed.
        $this->enrol('CARD-A', 'KA99ZZ9999', 'two_wheeler', 'Impostor')
            ->assertUnprocessable()
            ->assertJson(['error' => 'Vehicle is already parked']);

        $this->assertDatabaseHas('vehicles', [
            'rfid_id' => 'CARD-A',
            'vehicle_number' => 'KA01AB1234',
            'driver_name' => 'John',
        ]);

        // And the late submit must not open a second visit for the same vehicle.
        $this->assertSame(1, Entry::where('status', 'parked')->count());
    }

    public function test_entry_scan_parking_automatically_from_the_card_alone(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $vehicle = $this->makeVehicle('CARD-A', 'KA01AB1234', 'four_wheeler', 'John', '9876543210');

        $response = $this->scan('CARD-A', 'entry', self::LOT_A)
            ->assertOk()
            ->assertJson([
                'status' => 'parked',
                'rfid_id' => 'CARD-A',
                'vehicle_number' => 'KA01AB1234',
                'driver_name' => 'John',
                'vehicle_type' => 'four_wheeler',
                'lot' => self::LOT_A,
                'lot_name' => $lot->name,
            ]);

        // The visit is created in one step, so there is no details round-trip.
        $entry = Entry::findOrFail($response->json('entry_id'));

        $this->assertSame($vehicle->id, $entry->vehicle_id);
        $this->assertSame('CARD-A', $entry->rfid_id);
        $this->assertSame($lot->id, $entry->parking_lot_id);
        $this->assertSame('parked', $entry->status);
    }

    public function test_entry_does_not_ask_for_driver_details(): void
    {
        $this->makeLot(self::LOT_A);
        $this->makeVehicle();

        // The old pooled-card flow opened a details step here; it must no longer exist.
        $this->postJson('/api/rfid-scan/details', [
            'rfid_id' => 'CARD-A',
            'driver_name' => 'John',
            'vehicle_number' => 'KA01AB1234',
            'mobile_number' => '9876543210',
            'vehicle_type' => 'four_wheeler',
            'lot' => self::LOT_A,
        ])->assertNotFound();
    }

    public function test_entry_rejects_a_vehicle_that_is_already_parked(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $vehicle = $this->makeVehicle();
        $this->parkVehicle($vehicle, $lot);

        $this->scan('CARD-A', 'entry', self::LOT_A)
            ->assertUnprocessable()
            ->assertJson(['error' => 'Vehicle is already parked']);
    }

    public function test_the_details_endpoint_is_gone(): void
    {
        $routes = collect(app('router')->getRoutes())->map(fn ($route) => $route->uri());

        $this->assertFalse($routes->contains('api/rfid-scan/details'));
    }

    public function test_fee_uses_the_lot_rate_for_each_vehicle_type(): void
    {
        $lot = ParkingLot::create([
            'name' => 'Rate Test',
            'lot_number' => self::LOT_A,
            'rate_two_wheeler' => 15,
            'rate_four_wheeler' => 35,
        ]);

        $this->parkVehicle($this->makeVehicle('CARD-BIKE', 'KA01AA0001', 'two_wheeler'), $lot);
        $this->parkVehicle($this->makeVehicle('CARD-CAR', 'KA01AA0002', 'four_wheeler'), $lot);

        $this->scan('CARD-BIKE', 'exit', self::LOT_A)
            ->assertOk()
            ->assertJson(['amount' => 15]);

        $this->scan('CARD-CAR', 'exit', self::LOT_A)
            ->assertOk()
            ->assertJson(['amount' => 35]);
    }

    public function test_exit_at_the_same_lot_closes_the_entry(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $vehicle = $this->makeVehicle();
        $entry = $this->parkVehicle($vehicle, $lot);

        $this->scan('CARD-A', 'exit', self::LOT_A)
            ->assertOk()
            ->assertJson([
                'status' => 'exit',
                'entry_id' => $entry->id,
                'amount' => 20,
                'vehicle_number' => 'KA01AB1234',
            ]);

        $this->assertSame('exited', $entry->fresh()->status);
        $this->assertNotNull($entry->fresh()->exit_time);
    }

    public function test_exit_at_a_different_lot_is_rejected(): void
    {
        $lotA = $this->makeLot(self::LOT_A);
        $this->makeLot(self::LOT_B);
        $entry = $this->parkVehicle($this->makeVehicle(), $lotA);

        $this->scan('CARD-A', 'exit', self::LOT_B)
            ->assertUnprocessable()
            ->assertJson(['error' => 'Vehicle is parked at a different lot']);

        $this->assertSame('parked', $entry->fresh()->status);
        $this->assertNull($entry->fresh()->exit_time);
    }

    public function test_exit_with_no_active_entry_is_rejected(): void
    {
        $this->makeLot(self::LOT_A);
        $this->makeVehicle();

        $this->scan('CARD-A', 'exit', self::LOT_A)
            ->assertUnprocessable()
            ->assertJson(['error' => 'No active entry for this card']);
    }

    public function test_vehicle_can_re_enter_after_it_exits(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $vehicle = $this->makeVehicle();
        $this->parkVehicle($vehicle, $lot);

        $this->scan('CARD-A', 'exit', self::LOT_A)->assertOk();

        // The same card and the same vehicle come straight back in, with no new details.
        $this->scan('CARD-A', 'entry', self::LOT_A)
            ->assertOk()
            ->assertJson([
                'status' => 'parked',
                'vehicle_number' => 'KA01AB1234',
                'driver_name' => 'Owner',
            ]);
    }

    public function test_a_card_cannot_be_shared_by_two_vehicles(): void
    {
        $this->makeVehicle('CARD-A', 'KA01AB1234');

        // A card is one vehicle's identity, so a second registration of the same code
        // must be refused rather than silently reassigning the card.
        $this->expectException(QueryException::class);

        $this->makeVehicle('CARD-A', 'KA02CD5678');
    }

    public function test_vehicle_details_are_not_stored_on_the_entry(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $vehicle = $this->makeVehicle('CARD-A', 'KA01AB1234', 'four_wheeler', 'John', '9876543210');

        $response = $this->scan('CARD-A', 'entry', self::LOT_A)->assertOk();

        $entry = Entry::findOrFail($response->json('entry_id'));

        // Details live on the vehicle only, so they can be corrected once and apply everywhere.
        $this->assertArrayNotHasKey('driver_name', $entry->getAttributes());
        $this->assertArrayNotHasKey('vehicle_number', $entry->getAttributes());
        $this->assertArrayNotHasKey('mobile_number', $entry->getAttributes());
        $this->assertArrayNotHasKey('vehicle_type', $entry->getAttributes());

        $this->assertSame('John', $entry->vehicle->driver_name);
        $this->assertSame('9876543210', $entry->vehicle->mobile_number);
    }

    public function test_editing_vehicle_details_applies_to_new_visits(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $vehicle = $this->makeVehicle('CARD-A', 'KA01AB1234', 'four_wheeler', 'John');
        $this->parkVehicle($vehicle, $lot);

        $this->scan('CARD-A', 'exit', self::LOT_A)->assertOk();

        $vehicle->update(['driver_name' => 'Jane', 'vehicle_number' => 'KA09ZZ9999']);

        $this->scan('CARD-A', 'entry', self::LOT_A)
            ->assertOk()
            ->assertJson(['driver_name' => 'Jane', 'vehicle_number' => 'KA09ZZ9999']);
    }

    public function test_exit_still_works_for_a_visit_whose_vehicle_was_deleted(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $vehicle = $this->makeVehicle();
        $entry = $this->parkVehicle($vehicle, $lot);

        $vehicle->entries()->update(['vehicle_id' => null]);
        $vehicle->delete();

        $this->scan('CARD-A', 'exit', self::LOT_A)
            ->assertOk()
            ->assertJson(['status' => 'exit', 'entry_id' => $entry->id]);
    }

    public function test_kiosk_page_resolves_its_lot_from_the_database(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $this->makeKiosk($lot, Kiosk::TYPE_ENTRY, 'main-gate', 'Main Gate');

        $this->get('/?kiosk=main-gate')
            ->assertOk()
            ->assertSee('Main Gate')
            ->assertSee('PARKING LOT #'.self::LOT_A);
    }

    public function test_a_bound_kiosk_survives_a_browser_restart(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $this->makeKiosk($lot, Kiosk::TYPE_ENTRY, 'main-gate');

        // First visit binds the terminal and plants the long-lived cookie.
        $this->get('/?kiosk=main-gate')
            ->assertOk()
            ->assertCookie(ResolveKioskBinding::COOKIE_NAME);

        // A restart brings no session and no query string, only the cookie. The terminal
        // must still know its own gate, otherwise every scan fails until someone retypes
        // the URL. The session is dropped so the cookie is the only thing carrying the
        // binding - otherwise the in-session fallback would mask a broken cookie.
        $this->flushSession();

        $response = $this->withCookie(ResolveKioskBinding::COOKIE_NAME, 'main-gate')->get('/');

        $response->assertOk()
            ->assertSee('PARKING LOT #'.self::LOT_A)
            ->assertSee('This gate')
            ->assertSee('admits vehicles');
    }

    public function test_a_browser_with_no_kiosk_binding_is_not_pinned_to_one(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $this->makeKiosk($lot, Kiosk::TYPE_ENTRY, 'main-gate');

        // A shared machine that has never been bound must not inherit a gate, and must
        // not be given a binding by merely being looked at.
        $this->get('/')
            ->assertOk()
            ->assertSee('This kiosk is not linked to a parking lot')
            ->assertCookieMissing(ResolveKioskBinding::COOKIE_NAME);
    }

    public function test_an_unknown_kiosk_key_is_not_remembered(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $this->makeKiosk($lot, Kiosk::TYPE_ENTRY, 'main-gate');

        // A typo or a deleted kiosk must not be stored: binding to it would strand the
        // terminal on a gate that does not exist, on every later page load.
        $this->get('/?kiosk=deleted-gate')
            ->assertOk()
            ->assertSee('This kiosk is not linked to a parking lot')
            ->assertCookieMissing(ResolveKioskBinding::COOKIE_NAME);

        $this->assertNull(session('kiosk_key'));
    }

    public function test_a_kiosk_key_in_the_url_rebinds_a_terminal_that_is_already_bound(): void
    {
        $lotA = $this->makeLot(self::LOT_A);
        $lotB = $this->makeLot(self::LOT_B);
        $this->makeKiosk($lotA, Kiosk::TYPE_ENTRY, 'in-gate');
        $this->makeKiosk($lotB, Kiosk::TYPE_EXIT, 'out-gate');

        // Moving a tablet to a different gate is just opening that gate's URL; the new
        // binding overwrites the old one rather than needing an explicit unbind.
        $this->withCookie(ResolveKioskBinding::COOKIE_NAME, 'in-gate')
            ->get('/')
            ->assertOk()
            ->assertSee('PARKING LOT #'.self::LOT_A);

        $this->withCookie(ResolveKioskBinding::COOKIE_NAME, 'in-gate')
            ->get('/?kiosk=out-gate')
            ->assertOk()
            ->assertSee('PARKING LOT #'.self::LOT_B)
            ->assertSee('This gate')
            ->assertSee('releases vehicles');

        $this->assertSame('out-gate', session('kiosk_key'));
    }

    public function test_a_bound_terminal_can_be_released_again(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $this->makeKiosk($lot, Kiosk::TYPE_ENTRY, 'main-gate');

        // The cookie is remembered for a year, so a shared machine that once opened a
        // kiosk link needs a way back to the unbound state.
        $this->withCookie(ResolveKioskBinding::COOKIE_NAME, 'main-gate')
            ->get(route('kiosk.forget'))
            ->assertRedirect(route('home'))
            ->assertCookieExpired(ResolveKioskBinding::COOKIE_NAME);

        $this->assertNull(session('kiosk_key'));

        $this->flushSession();

        $this->get('/')
            ->assertOk()
            ->assertSee('This kiosk is not linked to a parking lot');
    }

    public function test_a_signed_in_browser_is_not_given_a_long_lived_binding(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $this->makeKiosk($lot, Kiosk::TYPE_ENTRY, 'main-gate', 'Main Gate');

        $this->actingAs($this->makeUser());

        // Staff reach a kiosk by clicking its link in the admin kiosk list, so the preview
        // still has to render. But a laptop is not gate hardware: a year-long cookie would
        // outlive the login and dump whoever used that machine back onto a gate view after
        // signing out. The session holds the selection instead, which ends at the logout.
        $this->get('/?kiosk=main-gate')
            ->assertOk()
            ->assertSee('Main Gate')
            ->assertSee('PARKING LOT #'.self::LOT_A)
            ->assertCookieExpired(ResolveKioskBinding::COOKIE_NAME);

        $this->assertSame('main-gate', session('kiosk_key'));
    }

    public function test_a_signed_in_browser_is_released_from_a_binding_it_already_had(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $this->makeKiosk($lot, Kiosk::TYPE_ENTRY, 'main-gate', 'Main Gate');

        // Machines bound before the role existed still carry the cookie, so signing in has
        // to clear it rather than merely decline to refresh it - otherwise those laptops stay
        // pinned to the gate for the full year. Clearing the cookie must not cost the signed
        // in user the gate they are actually on, which is why this clears the cookie alone.
        $this->withCookie(ResolveKioskBinding::COOKIE_NAME, 'main-gate');

        $this->actingAs($this->makeUser());

        $this->get('/')
            ->assertOk()
            ->assertSee('Main Gate')
            ->assertCookieExpired(ResolveKioskBinding::COOKIE_NAME);

        $this->assertSame('main-gate', session('kiosk_key'));
    }

    public function test_a_signed_in_browser_keeps_its_kiosk_after_navigating_away(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $this->makeKiosk($lot, Kiosk::TYPE_ENTRY, 'main-gate', 'Main Gate');

        $this->actingAs($this->makeUser());

        $this->get('/?kiosk=main-gate')->assertOk();

        // The point of the session binding: the URL only has to carry the selection once.
        // Reaching the site through the nav or a refresh drops the query string, and an
        // operator or admin who picked a gate should still be standing at it.
        $this->get('/')
            ->assertOk()
            ->assertSee('Main Gate')
            ->assertSee('PARKING LOT #'.self::LOT_A);
    }

    public function test_a_signed_in_browsers_kiosk_selection_ends_at_logout(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $this->makeKiosk($lot, Kiosk::TYPE_ENTRY, 'main-gate', 'Main Gate');

        $this->actingAs($this->makeUser());

        $this->get('/?kiosk=main-gate')->assertOk();

        $this->post('/logout')->assertRedirect('/login');

        // A login is the scope: the next person to sign in on this machine is not left at
        // whatever gate the last user happened to be looking at.
        $this->get('/')
            ->assertOk()
            ->assertDontSee('Main Gate');
    }

    public function test_a_kiosk_url_still_overrides_the_selection_from_the_login(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $this->makeKiosk($lot, Kiosk::TYPE_ENTRY, 'main-gate', 'Main Gate');
        $this->makeKiosk($lot, Kiosk::TYPE_EXIT, 'side-gate', 'Side Gate');

        $this->actingAs($this->makeUser());

        $this->get('/?kiosk=main-gate')->assertOk();

        // Switching gate is still just opening the other URL, which is how the admin kiosk
        // list works. The session is a fallback, not a claim that nothing else may override it.
        $this->get('/?kiosk=side-gate')
            ->assertOk()
            ->assertSee('Side Gate')
            ->assertDontSee('Main Gate');

        $this->assertSame('side-gate', session('kiosk_key'));
    }

    public function test_an_anonymous_terminal_keeps_its_binding_across_the_session(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $this->makeKiosk($lot, Kiosk::TYPE_ENTRY, 'main-gate', 'Main Gate');

        $this->get('/?kiosk=main-gate')->assertOk();

        $this->assertSame('main-gate', session('kiosk_key'));

        // The cookie is what survives a restart, but the session is read as a source too, so
        // a terminal that loses its cookie mid-shift still resolves rather than silently
        // falling back to the unbound page.
        $this->get('/')
            ->assertOk()
            ->assertSee('Main Gate');
    }

    public function test_a_signed_in_browser_is_not_offered_an_unbind_it_cannot_use(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $this->makeKiosk($lot, Kiosk::TYPE_ENTRY, 'main-gate');

        $this->actingAs($this->makeUser());

        // The escape hatch is for terminals holding a durable binding, where it is the only
        // way off the gate. A signed-in browser's selection ends at its logout on its own, and
        // the admin kiosk list is how you move to a different gate, so the link would be a
        // button that appears to achieve nothing the user wanted.
        $this->get('/?kiosk=main-gate')
            ->assertOk()
            ->assertDontSee('Not this kiosk? Unbind');
    }

    public function test_kiosk_page_warns_when_not_linked_to_a_lot(): void
    {
        $this->makeLot(self::LOT_A);

        $this->get('/?kiosk=unknown-key')
            ->assertOk()
            ->assertSee('This kiosk is not linked to a parking lot');
    }

    public function test_delinked_kiosk_scan_is_refused(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $this->makeKiosk($lot, Kiosk::TYPE_ENTRY, 'main-gate');
        Kiosk::where('key', 'main-gate')->update(['parking_lot_id' => null]);

        $this->postJson('/api/rfid-scan', [
            'rfid_id' => 'CARD-A',
            'lot' => self::LOT_A,
            'kiosk' => 'main-gate',
        ])->assertUnprocessable()
            ->assertJson(['error' => 'Kiosk is not linked to a parking lot']);
    }

    public function test_kiosk_key_for_a_different_lot_is_refused(): void
    {
        $this->makeLot(self::LOT_A);
        $lotB = $this->makeLot(self::LOT_B);
        $this->makeKiosk($lotB, Kiosk::TYPE_ENTRY, 'entry-gate');

        $this->postJson('/api/rfid-scan', [
            'rfid_id' => 'CARD-A',
            'lot' => self::LOT_A,
            'kiosk' => 'entry-gate',
        ])->assertUnprocessable()
            ->assertJson(['error' => 'Kiosk is not linked to this parking lot']);
    }

    public function test_a_scan_must_name_the_kiosk_that_made_it(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $this->makeKiosk($lot, Kiosk::TYPE_ENTRY, 'main-gate');

        // Without a kiosk there is no way to tell an entry from an exit, and guessing could
        // let a vehicle out without being charged.
        $this->postJson('/api/rfid-scan', [
            'rfid_id' => 'CARD-A',
            'lot' => self::LOT_A,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('kiosk');
    }

    public function test_an_entry_kiosk_admits_and_an_exit_kiosk_releases(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $entry = $this->makeKiosk($lot, Kiosk::TYPE_ENTRY, 'in-gate');
        $exit = $this->makeKiosk($lot, Kiosk::TYPE_EXIT, 'out-gate');
        $this->makeVehicle('CARD-A', 'KA01AB1234', 'four_wheeler', 'John', '9876543210');

        // The direction is never sent by the client: the entry gate parks.
        $this->postJson('/api/rfid-scan', ['rfid_id' => 'CARD-A', 'lot' => self::LOT_A, 'kiosk' => $entry->key])
            ->assertOk()
            ->assertJson(['status' => 'parked']);

        // ...and the exit gate releases the same card, charging the fee.
        $this->postJson('/api/rfid-scan', ['rfid_id' => 'CARD-A', 'lot' => self::LOT_A, 'kiosk' => $exit->key])
            ->assertOk()
            ->assertJson(['status' => 'exit']);

        // The visit really was closed by the exit gate, not just reported as released.
        $this->assertNotNull(Entry::where('rfid_id', 'CARD-A')->latest()->value('exit_time'));
    }

    public function test_an_exit_kiosk_cannot_admit_a_vehicle_that_is_not_parked(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $exit = $this->makeKiosk($lot, Kiosk::TYPE_EXIT, 'out-gate');
        $this->makeVehicle('CARD-A', 'KA01AB1234', 'four_wheeler', 'John', '9876543210');

        // An exit gate only releases. Scanning a card that is not inside must not create a visit.
        $this->postJson('/api/rfid-scan', ['rfid_id' => 'CARD-A', 'lot' => self::LOT_A, 'kiosk' => $exit->key])
            ->assertUnprocessable()
            ->assertJson(['error' => 'No active entry for this card']);

        $this->assertDatabaseCount('entries', 0);
    }

    public function test_an_entry_kiosk_cannot_release_a_vehicle_that_is_not_parked(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $entry = $this->makeKiosk($lot, Kiosk::TYPE_ENTRY, 'in-gate');
        $this->makeVehicle('CARD-A', 'KA01AB1234', 'four_wheeler', 'John', '9876543210');

        // An entry gate only admits, so it refuses the release instead of parking the car.
        $this->postJson('/api/rfid-scan', ['rfid_id' => 'CARD-A', 'lot' => self::LOT_A, 'kiosk' => $entry->key])
            ->assertOk()
            ->assertJson(['status' => 'parked']);

        $this->assertDatabaseHas('entries', [
            'rfid_id' => 'CARD-A',
            'entry_kiosk_key' => 'in-gate',
            'exit_kiosk_key' => null,
        ]);
    }

    public function test_a_kiosk_with_no_gate_type_refuses_scans(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $kiosk = $this->makeKiosk($lot, Kiosk::TYPE_ENTRY, 'main-gate');
        $this->makeVehicle('CARD-A', 'KA01AB1234', 'four_wheeler', 'John', '9876543210');

        $kiosk->update(['type' => null]);

        $this->postJson('/api/rfid-scan', ['rfid_id' => 'CARD-A', 'lot' => self::LOT_A, 'kiosk' => 'main-gate'])
            ->assertUnprocessable()
            ->assertJson(['error' => 'This kiosk is not set to ENTRY or EXIT. Set it in the admin panel.']);

        $this->assertDatabaseCount('entries', 0);
    }

    public function test_enrolment_is_refused_at_an_exit_kiosk(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $exit = $this->makeKiosk($lot, Kiosk::TYPE_EXIT, 'out-gate');

        // Enrolment parks the vehicle, and an exit gate has no visit to attach it to.
        $this->postJson('/api/rfid-scan/enrol', [
            'rfid_id' => 'CARD-A',
            'driver_name' => 'John',
            'mobile_number' => '9876543210',
            'vehicle_number' => 'KA01AB1234',
            'vehicle_type' => 'four_wheeler',
            'lot' => self::LOT_A,
            'kiosk' => $exit->key,
        ])->assertUnprocessable()
            ->assertJson(['error' => 'Cards can only be enrolled at an ENTRY kiosk']);

        $this->assertDatabaseCount('vehicles', 0);
    }
}
