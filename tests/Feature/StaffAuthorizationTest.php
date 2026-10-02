<?php

namespace Tests\Feature;

use App\Livewire\Admin\FloorManager;
use App\Livewire\Admin\KioskManager;
use App\Livewire\Admin\LotManager;
use App\Livewire\Admin\VehicleManager;
use App\Livewire\Auth\Login;
use App\Models\Kiosk;
use App\Models\ParkingFloor;
use App\Models\ParkingLot;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithStaff;
use Tests\TestCase;

/**
 * The staff authorisation surface: who can reach what, and which lot a record has to
 * belong to before it is touchable.
 */
class StaffAuthorizationTest extends TestCase
{
    use InteractsWithStaff;

    private ParkingLot $lotA;

    private ParkingLot $lotB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpStaff();

        // Allocated through the model rather than hard-coded: an earlier migration leaves
        // a "Main Lot" behind, so lot numbers 1 and 2 are not reliably free.
        $this->lotA = ParkingLot::create([
            'name' => 'Lot A', 'lot_number' => ParkingLot::nextLotNumber(),
            'rate_two_wheeler' => 10, 'rate_four_wheeler' => 20,
        ]);

        $this->lotB = ParkingLot::create([
            'name' => 'Lot B', 'lot_number' => ParkingLot::nextLotNumber(),
            'rate_two_wheeler' => 10, 'rate_four_wheeler' => 20,
        ]);
    }

    private function floorIn(ParkingLot $lot, string $name = 'Ground'): ParkingFloor
    {
        return ParkingFloor::create([
            'parking_lot_id' => $lot->id, 'name' => $name, 'floor_number' => 1, 'slot_count' => 2,
        ]);
    }

    // ── Authentication boundary ────────────────────────────────────────────────

    public function test_admin_pages_redirect_an_anonymous_visitor_to_login(): void
    {
        foreach (['admin.lots', 'admin.floors', 'admin.kiosks', 'admin.vehicles'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    public function test_the_kiosk_terminal_and_live_dashboards_stay_public(): void
    {
        // A gate tablet and a wall monitor have no account, and the occupancy figures they
        // show are not sensitive. Locking these would break the gate to no security gain.
        $this->get(route('home'))->assertOk();
        $this->get(route('lots.overview'))->assertOk();
        $this->get(route('slots.dashboard'))->assertOk();
    }

    public function test_a_signed_in_admin_is_sent_onwards_from_the_login_page(): void
    {
        $this->actingAsSuperAdmin();

        $this->get(route('login'))->assertRedirect();
    }

    public function test_an_admin_can_sign_in_with_valid_credentials(): void
    {
        $user = $this->superAdmin(['email' => 'boss@parkeasy.test', 'password' => Hash::make('correct-horse')]);

        Livewire::test(Login::class)
            ->set('email', 'boss@parkeasy.test')
            ->set('password', 'correct-horse')
            ->call('login')
            ->assertRedirect(route('admin.lots'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_password_is_rejected_without_revealing_which_part_failed(): void
    {
        $this->superAdmin(['email' => 'boss@parkeasy.test', 'password' => Hash::make('correct-horse')]);

        Livewire::test(Login::class)
            ->set('email', 'boss@parkeasy.test')
            ->set('password', 'wrong')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();

        // A message naming the email as unknown would turn the form into an account oracle.
        $message = Livewire::test(Login::class)
            ->set('email', 'nobody@parkeasy.test')
            ->set('password', 'whatever')
            ->call('login')
            ->errors()->get('email')[0];

        $this->assertStringNotContainsString('nobody@parkeasy.test', $message);
    }

    public function test_repeated_failures_are_throttled(): void
    {
        $this->superAdmin(['email' => 'boss@parkeasy.test', 'password' => Hash::make('correct-horse')]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            Livewire::test(Login::class)
                ->set('email', 'boss@parkeasy.test')
                ->set('password', 'wrong')
                ->call('login');
        }

        $message = Livewire::test(Login::class)
            ->set('email', 'boss@parkeasy.test')
            ->set('password', 'correct-horse')   // the right password, still refused
            ->call('login')
            ->errors()->get('email')[0];

        $this->assertStringContainsString('Too many attempts', $message);
        $this->assertGuest();
    }

    public function test_signing_out_ends_the_session(): void
    {
        $this->actingAsSuperAdmin();

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    // ── Lot scoping: what a lot admin can see ──────────────────────────────────

    public function test_a_lot_admin_sees_only_their_own_lots(): void
    {
        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(LotManager::class)
            ->assertSee('Lot A')
            ->assertDontSee('Lot B');
    }

    public function test_a_lot_admin_is_offered_a_link_back_to_the_page_they_land_on(): void
    {
        $this->actingAsLotAdmin([$this->lotA]);

        // Sign-in sends a lot admin to /admin/lots and ParkingLotPolicy::viewAny lets them
        // read it, so the nav has to offer it too. Gating the link on lots.manage instead
        // meant they could arrive on the page but had no way to return to it.
        //
        // Asserted against the nav alone: other admin pages link to /admin/lots as well, so
        // a whole-page assertion would pass even with the link removed from the nav.
        $this->assertStringContainsString(
            route('admin.lots'),
            $this->navHtml()
        );
    }

    public function test_a_guest_is_not_offered_the_admin_lots_link(): void
    {
        $this->assertStringNotContainsString(route('admin.lots'), $this->navHtml());
    }

    public function test_the_nav_names_the_lot_a_lot_admin_is_in(): void
    {
        $this->lotB->update(['name' => 'Riverside']);
        $this->actingAsLotAdmin([$this->lotB]);

        // Everything a lot admin can reach is scoped to the lot they hold, so the lot's
        // name is the fact worth having to hand - it was only in a hover title before.
        $this->assertStringContainsString('Riverside', $this->navHtml());
    }

    public function test_the_nav_says_how_many_lots_a_lot_admin_holds_rather_than_truncating_them(): void
    {
        $this->lotA->update(['name' => 'Central Garage']);
        $this->lotB->update(['name' => 'Riverside']);
        $this->actingAsLotAdmin([$this->lotA, $this->lotB]);

        $nav = $this->navHtml();

        // Two lots cannot both fit in the badge, so it names one and counts the rest.
        // Truncating instead would leave the operator unable to tell one lot from three.
        $this->assertStringContainsString('Central Garage +1', $nav);
        $this->assertStringContainsString('Lot admin of Central Garage, Riverside', $nav);
    }

    public function test_the_nav_falls_back_to_the_role_for_a_lot_admin_holding_no_lots(): void
    {
        $this->actingAsLotAdmin([]);

        // An admin with no assignment is a real state - an empty badge would read as a bug.
        $this->assertStringContainsString('Lot admin', $this->navHtml());
    }

    public function test_a_lot_admin_sees_only_their_own_floors(): void
    {
        $this->floorIn($this->lotA, 'Ground A');
        $this->floorIn($this->lotB, 'Ground B');

        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(FloorManager::class)
            ->assertSee('Ground A')
            ->assertDontSee('Ground B');
    }

    public function test_a_lot_admin_cannot_widen_the_scope_with_the_lot_query_parameter(): void
    {
        // Otherwise ?lot= is a straight read of any lot's floors, bypassing the list scope.
        $this->floorIn($this->lotB, 'Ground B');

        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::withQueryParams(['lot' => $this->lotB->id])
            ->test(FloorManager::class)
            ->assertDontSee('Ground B');
    }

    public function test_a_lot_admin_with_no_assignment_sees_nothing_rather_than_everything(): void
    {
        // Failing closed: an unassigned admin must get an empty view, not the whole site.
        $this->floorIn($this->lotA, 'Ground A');

        $this->actingAsLotAdmin([]);

        Livewire::test(FloorManager::class)->assertDontSee('Ground A');
        Livewire::test(LotManager::class)->assertDontSee('Lot A');
    }

    public function test_a_super_admin_sees_every_lot(): void
    {
        $this->floorIn($this->lotA, 'Ground A');
        $this->floorIn($this->lotB, 'Ground B');

        $this->actingAsSuperAdmin();

        Livewire::test(FloorManager::class)
            ->assertSee('Ground A')
            ->assertSee('Ground B');
    }

    // ── Lot scoping: what a lot admin can change ───────────────────────────────

    public function test_a_lot_admin_cannot_add_a_floor_to_someone_elses_lot(): void
    {
        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(FloorManager::class)
            ->set('lotId', $this->lotB->id)
            ->set('name', 'Sneaky')
            ->set('slotCount', '3')
            ->call('addFloor')
            ->assertForbidden();

        $this->assertDatabaseMissing('parking_floors', ['name' => 'Sneaky']);
    }

    public function test_a_lot_admin_cannot_edit_a_floor_in_another_lot(): void
    {
        $foreign = $this->floorIn($this->lotB, 'Ground B');

        $this->actingAsLotAdmin([$this->lotA]);

        // Opening the form is refused...
        Livewire::test(FloorManager::class)
            ->call('startEdit', $foreign->id)
            ->assertForbidden();

        // ...and so is saving, for a client that sets the properties without ever being
        // let into the form. One check would be enough for the honest client and useless
        // against the dishonest one.
        Livewire::test(FloorManager::class)
            ->set('editingId', $foreign->id)
            ->set('editLotId', $this->lotB->id)
            ->set('editName', 'Hijacked')
            ->set('editSlotCount', '1')
            ->call('saveEdit')
            ->assertForbidden();

        $this->assertDatabaseHas('parking_floors', ['id' => $foreign->id, 'name' => 'Ground B']);
    }

    public function test_a_lot_admin_cannot_delete_a_floor_in_another_lot(): void
    {
        $foreign = $this->floorIn($this->lotB, 'Ground B');

        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(FloorManager::class)
            ->set('confirmDeleteId', $foreign->id)
            ->call('deleteFloor')
            ->assertForbidden();

        $this->assertDatabaseHas('parking_floors', ['id' => $foreign->id]);
    }

    public function test_a_lot_admin_cannot_reassign_a_slot_type_in_another_lot(): void
    {
        $floor = $this->floorIn($this->lotB);
        $floor->syncSlots();
        $slot = $floor->slots()->firstOrFail();

        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(FloorManager::class)
            ->call('toggleSlotType', $slot->id)
            ->assertForbidden();
    }

    public function test_a_lot_admin_cannot_move_a_floor_into_a_lot_they_do_not_administer(): void
    {
        $own = $this->floorIn($this->lotA, 'Ground A');

        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(FloorManager::class)
            ->set('editingId', $own->id)
            ->set('editLotId', $this->lotB->id)
            ->set('editName', 'Ground A')
            ->set('editSlotCount', '2')
            ->call('saveEdit')
            ->assertForbidden();

        $this->assertDatabaseHas('parking_floors', ['id' => $own->id, 'parking_lot_id' => $this->lotA->id]);
    }

    public function test_a_lot_admin_can_still_manage_their_own_floor(): void
    {
        $own = $this->floorIn($this->lotA, 'Ground A');

        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(FloorManager::class)
            ->set('editingId', $own->id)
            ->set('editLotId', $this->lotA->id)
            ->set('editName', 'Renamed')
            ->set('editSlotCount', '2')
            ->call('saveEdit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('parking_floors', ['id' => $own->id, 'name' => 'Renamed']);
    }

    // ── Lots themselves are super-admin territory ──────────────────────────────

    public function test_a_lot_admin_cannot_create_a_parking_lot(): void
    {
        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(LotManager::class)
            ->set('name', 'New Lot')
            ->set('rateTwoWheeler', '10')
            ->set('rateFourWheeler', '20')
            ->call('add')
            ->assertForbidden();

        $this->assertDatabaseMissing('parking_lots', ['name' => 'New Lot']);
    }

    public function test_a_lot_admin_cannot_delete_a_parking_lot(): void
    {
        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(LotManager::class)
            ->set('deletingId', $this->lotA->id)
            ->call('delete')
            ->assertForbidden();

        $this->assertDatabaseHas('parking_lots', ['id' => $this->lotA->id]);
    }

    public function test_the_lot_manager_hides_creation_controls_from_a_lot_admin(): void
    {
        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(LotManager::class)
            ->assertDontSee('Add New Parking Lot')
            ->assertSee('Your Parking Lots');
    }

    // ── Kiosks ─────────────────────────────────────────────────────────────────

    public function test_a_lot_admin_sees_only_kiosks_on_their_own_lots(): void
    {
        $mine = Kiosk::create(['name' => 'A Entry', 'key' => 'a-entry', 'type' => Kiosk::TYPE_ENTRY, 'parking_lot_id' => $this->lotA->id]);
        $theirs = Kiosk::create(['name' => 'B Entry', 'key' => 'b-entry', 'type' => Kiosk::TYPE_ENTRY, 'parking_lot_id' => $this->lotB->id]);
        $unattached = Kiosk::create(['name' => 'Spare Kiosk', 'key' => 'spare', 'type' => Kiosk::TYPE_ENTRY, 'parking_lot_id' => null]);

        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(KioskManager::class)
            ->assertSee('A Entry')
            ->assertDontSee('B Entry')
            // An unattached kiosk belongs to nobody, so a lot admin must not be able to
            // quietly claim it for their own lot.
            ->assertDontSee('Spare Kiosk');
    }

    public function test_a_super_admin_sees_unattached_kiosks(): void
    {
        Kiosk::create(['name' => 'Spare Kiosk', 'key' => 'spare', 'type' => Kiosk::TYPE_ENTRY, 'parking_lot_id' => null]);

        $this->actingAsSuperAdmin();

        Livewire::test(KioskManager::class)->assertSee('Spare Kiosk');
    }

    public function test_a_lot_admin_cannot_attach_a_kiosk_to_a_lot_they_do_not_administer(): void
    {
        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(KioskManager::class)
            ->set('name', 'Sneaky Kiosk')
            ->set('lotId', $this->lotB->id)
            ->set('type', Kiosk::TYPE_ENTRY)
            ->call('add')
            ->assertForbidden();

        $this->assertDatabaseMissing('kiosks', ['name' => 'Sneaky Kiosk']);
    }

    public function test_a_lot_admin_cannot_delete_a_kiosk_on_another_lot(): void
    {
        $theirs = Kiosk::create(['name' => 'B Entry', 'key' => 'b-entry', 'type' => Kiosk::TYPE_ENTRY, 'parking_lot_id' => $this->lotB->id]);

        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(KioskManager::class)
            ->set('deletingId', $theirs->id)
            ->call('delete')
            ->assertForbidden();

        $this->assertDatabaseHas('kiosks', ['id' => $theirs->id]);
    }

    public function test_a_lot_admin_cannot_detach_a_kiosk_from_its_lot(): void
    {
        // Detaching produces a kiosk no lot admin can see, so it is a super-admin move.
        $mine = Kiosk::create(['name' => 'A Entry', 'key' => 'a-entry', 'type' => Kiosk::TYPE_ENTRY, 'parking_lot_id' => $this->lotA->id]);

        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(KioskManager::class)
            ->call('delink', $mine->id)
            ->assertForbidden();

        $this->assertDatabaseHas('kiosks', ['id' => $mine->id, 'parking_lot_id' => $this->lotA->id]);
    }

    // ── The vehicle registry is site-wide ──────────────────────────────────────

    public function test_a_lot_admin_can_bind_a_card_to_a_vehicle(): void
    {
        $admin = $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(VehicleManager::class)
            ->set('rfidId', 'CARD-NEW')
            ->set('vehicleNumber', 'ML01AB1234')
            ->set('vehicleType', 'four_wheeler')
            ->set('driverName', 'A Driver')
            ->set('mobileNumber', '9876543210')
            ->call('add')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vehicles', ['rfid_id' => 'CARD-NEW']);

        // The registry is global, so the binding is attributed to the admin and to no lot.
        $this->assertDatabaseHas('vehicles', [
            'rfid_id' => 'CARD-NEW', 'registered_by_user_id' => $admin->id,
        ]);
    }

    public function test_a_registration_is_rejected_site_wide_even_from_another_lot(): void
    {
        // The same card already exists from an arrival at the other lot. Because the
        // registry is global, a second row must be refused - this is the whole reason the
        // registry is not lot-scoped.
        Vehicle::create([
            'rfid_id' => 'CARD-1', 'vehicle_number' => 'ML01AB1234',
            'vehicle_type' => 'four_wheeler', 'driver_name' => 'First', 'mobile_number' => '9876543210',
        ]);

        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(VehicleManager::class)
            ->set('rfidId', 'CARD-1')
            ->set('vehicleNumber', 'ML99ZZ9999')
            ->set('vehicleType', 'four_wheeler')
            ->set('driverName', 'Impostor')
            ->set('mobileNumber', '9876543211')
            ->call('add')
            ->assertHasErrors('rfidId');
    }

    public function test_a_registration_number_is_unique_across_lots(): void
    {
        Vehicle::create([
            'rfid_id' => 'CARD-1', 'vehicle_number' => 'ML01AB1234',
            'vehicle_type' => 'four_wheeler', 'driver_name' => 'First', 'mobile_number' => '9876543210',
        ]);

        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(VehicleManager::class)
            ->set('rfidId', 'CARD-2')
            ->set('vehicleNumber', 'ML01AB1234')
            ->set('vehicleType', 'four_wheeler')
            ->set('driverName', 'Second')
            ->set('mobileNumber', '9876543211')
            ->call('add')
            ->assertHasErrors('vehicleNumber');
    }

    public function test_a_lot_admin_cannot_edit_a_vehicle_registration(): void
    {
        $vehicle = Vehicle::create([
            'rfid_id' => 'CARD-1', 'vehicle_number' => 'ML01AB1234',
            'vehicle_type' => 'four_wheeler', 'driver_name' => 'First', 'mobile_number' => '9876543210',
        ]);

        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(VehicleManager::class)
            ->call('edit', $vehicle->id)
            ->assertForbidden();
    }

    public function test_a_lot_admin_cannot_delete_a_vehicle_registration(): void
    {
        $vehicle = Vehicle::create([
            'rfid_id' => 'CARD-1', 'vehicle_number' => 'ML01AB1234',
            'vehicle_type' => 'four_wheeler', 'driver_name' => 'First', 'mobile_number' => '9876543210',
        ]);

        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(VehicleManager::class)
            ->set('deletingId', $vehicle->id)
            ->call('delete')
            ->assertForbidden();

        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id]);
    }

    public function test_a_lot_admin_can_look_up_a_vehicle_registered_by_another_lot(): void
    {
        // Read is deliberately open: a card has to be looked up at whichever gate the
        // vehicle turns up at, so the registry cannot be filtered by lot.
        Vehicle::create([
            'rfid_id' => 'CARD-ELSEWHERE', 'vehicle_number' => 'ML99ZZ9999',
            'vehicle_type' => 'four_wheeler', 'driver_name' => 'Visiting Driver', 'mobile_number' => '9876543210',
        ]);

        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(VehicleManager::class)
            ->set('search', 'ML99ZZ9999')
            ->assertSee('CARD-ELSEWHERE');
    }

    public function test_the_vehicle_list_hides_edit_and_delete_from_a_lot_admin(): void
    {
        // Attributed to the admin's own lot, which is the only reason it is in their list.
        Vehicle::create([
            'rfid_id' => 'CARD-1', 'vehicle_number' => 'ML01AB1234',
            'vehicle_type' => 'four_wheeler', 'driver_name' => 'First', 'mobile_number' => '9876543210',
            'registered_at_lot_id' => $this->lotA->id,
        ]);

        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(VehicleManager::class)
            ->assertSee('CARD-1')
            // The add form stays: binding a card on arrival is a gate necessity.
            ->assertSee('Register Vehicle & Card')
            ->assertDontSee('wire:click="edit(')
            ->assertDontSee('wire:click="confirmDelete(');
    }

    public function test_a_super_admin_can_edit_and_delete_a_vehicle(): void
    {
        $vehicle = Vehicle::create([
            'rfid_id' => 'CARD-1', 'vehicle_number' => 'ML01AB1234',
            'vehicle_type' => 'four_wheeler', 'driver_name' => 'First', 'mobile_number' => '9876543210',
        ]);

        $this->actingAsSuperAdmin();

        Livewire::test(VehicleManager::class)->call('edit', $vehicle->id);
        Livewire::test(VehicleManager::class)
            ->set('deletingId', $vehicle->id)
            ->call('delete');

        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
    }

    // ── Slot reach is checked through the floor ────────────────────────────────

    public function test_slot_authorisation_walks_slot_to_floor_to_lot(): void
    {
        $foreignFloor = $this->floorIn($this->lotB);
        $foreignFloor->syncSlots();
        $foreignSlot = $foreignFloor->slots()->firstOrFail();

        $ownFloor = $this->floorIn($this->lotA);
        $ownFloor->syncSlots();
        $ownSlot = $ownFloor->slots()->firstOrFail();

        $admin = $this->lotAdmin([$this->lotA]);

        $this->assertTrue($admin->can('update', $ownSlot));
        $this->assertFalse($admin->can('update', $foreignSlot));
    }

    // ── The authorisation surface itself ───────────────────────────────────────

    public function test_a_user_with_no_role_can_sign_in_but_see_no_admin_data(): void
    {
        // Authentication and authorisation are separate: having a valid account must not
        // by itself grant access to a single lot or record.
        $user = User::create([
            'name' => 'No Role', 'email' => 'norole@parkeasy.test', 'password' => Hash::make('password'),
        ]);

        $this->actingAs($user);

        foreach (['admin.lots', 'admin.floors', 'admin.kiosks', 'admin.vehicles'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }

    public function test_a_role_assignment_actually_changes_reach(): void
    {
        $user = $this->lotAdmin([$this->lotA], ['email' => 'mover@parkeasy.test']);

        $this->assertTrue($user->administersLot($this->lotA));
        $this->assertFalse($user->administersLot($this->lotB));

        $user->lots()->sync([$this->lotB->id]);
        $user = $user->fresh();

        $this->assertFalse($user->administersLot($this->lotA));
        $this->assertTrue($user->administersLot($this->lotB));
    }

    private function navHtml(): string
    {
        return (string) $this->blade('<x-site-nav />');
    }
}
