<?php

namespace Tests\Feature;

use App\Livewire\Admin\VehicleManager;
use App\Models\ParkingLot;
use App\Models\Vehicle;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithStaff;
use Tests\TestCase;

/**
 * "Recently added" is scoped to the lot the card was bound at; search is not.
 *
 * That split is the whole design. The list answers "what has this gate been handed today",
 * which is only useful if it is this gate's own work. Search answers "whose card is this",
 * which has to reach every lot or a driver who turns up at the wrong gate has no way forward.
 */
class VehicleRecentByLotTest extends TestCase
{
    use InteractsWithStaff;

    private ParkingLot $lotA;

    private ParkingLot $lotB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpStaff();

        $this->lotA = ParkingLot::create([
            'name' => 'Lot A', 'lot_number' => ParkingLot::nextLotNumber(),
            'rate_two_wheeler' => 10, 'rate_four_wheeler' => 20,
        ]);

        $this->lotB = ParkingLot::create([
            'name' => 'Lot B', 'lot_number' => ParkingLot::nextLotNumber(),
            'rate_two_wheeler' => 10, 'rate_four_wheeler' => 20,
        ]);
    }

    private function vehicle(string $rfid, ?ParkingLot $lot = null, int $daysAgo = 0): Vehicle
    {
        return Vehicle::create([
            'rfid_id' => $rfid,
            'vehicle_number' => 'KA'.strtoupper(substr(md5($rfid), 0, 4)).'AB1234',
            'vehicle_type' => 'four_wheeler',
            'driver_name' => 'Driver '.$rfid,
            'mobile_number' => '987654'.str_pad((string) abs(crc32($rfid)) % 10000, 4, '0', STR_PAD_LEFT),
            'registered_at_lot_id' => $lot?->id,
            'created_at' => now()->subDays($daysAgo),
        ]);
    }

    public function test_a_lot_admin_sees_only_their_own_lots_recent_registrations(): void
    {
        $this->vehicle('CARD-MINE', $this->lotA);
        $this->vehicle('CARD-THEIRS', $this->lotB, 1);

        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(VehicleManager::class)
            ->assertSee('CARD-MINE')
            ->assertDontSee('CARD-THEIRS');
    }

    public function test_a_newer_card_at_another_lot_does_not_displace_their_own(): void
    {
        // "Most recent" is computed within the lot, not globally and then filtered. If it
        // were filtered after ordering, a busy neighbouring lot would empty this list.
        $this->vehicle('CARD-MINE-OLD', $this->lotA, 5);
        $this->vehicle('CARD-OLD-BUT-MINE', $this->lotA, 4);
        $this->vehicle('CARD-THEIRS-NEW', $this->lotB, 0);

        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(VehicleManager::class)
            ->assertSee('CARD-MINE-OLD')
            ->assertSee('CARD-OLD-BUT-MINE')
            ->assertDontSee('CARD-THEIRS-NEW');
    }

    public function test_search_still_reaches_a_card_registered_at_another_lot(): void
    {
        $this->vehicle('CARD-THEIRS', $this->lotB);

        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(VehicleManager::class)
            ->set('search', 'CARD-THEIRS')
            ->assertSee('CARD-THEIRS');
    }

    public function test_a_card_with_no_lot_is_absent_from_the_list_but_findable_by_search(): void
    {
        // Registered by a super admin who left the origin blank. It belongs to no lot, so it
        // must not appear in anyone's "recently added" - but it still has to be findable.
        $this->vehicle('CARD-ORPHAN', null);

        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(VehicleManager::class)->assertDontSee('CARD-ORPHAN');

        Livewire::test(VehicleManager::class)
            ->set('search', 'CARD-ORPHAN')
            ->assertSee('CARD-ORPHAN');
    }

    public function test_a_super_admin_still_sees_a_site_wide_recent_feed(): void
    {
        $this->vehicle('CARD-A-LOT', $this->lotA);
        $this->vehicle('CARD-B-LOT', $this->lotB, 1);
        $this->vehicle('CARD-NO-LOT', null, 2);

        $this->actingAsSuperAdmin();

        Livewire::test(VehicleManager::class)
            ->assertSee('CARD-A-LOT')
            ->assertSee('CARD-B-LOT')
            ->assertSee('CARD-NO-LOT');
    }

    public function test_an_admin_of_several_lots_sees_the_combination(): void
    {
        $this->vehicle('CARD-A', $this->lotA);
        $this->vehicle('CARD-B', $this->lotB, 1);
        $this->vehicle('CARD-C', null, 2);

        $this->actingAsLotAdmin([$this->lotA, $this->lotB]);

        Livewire::test(VehicleManager::class)
            ->assertSee('CARD-A')
            ->assertSee('CARD-B')
            ->assertDontSee('CARD-C');
    }

    public function test_a_lot_admin_with_no_lot_sees_an_empty_list_rather_than_everyones(): void
    {
        $this->vehicle('CARD-A', $this->lotA);
        $this->vehicle('CARD-B', $this->lotB, 1);

        $this->actingAsLotAdmin([]);

        Livewire::test(VehicleManager::class)
            ->assertDontSee('CARD-A')
            ->assertDontSee('CARD-B')
            ->assertSee('No cards have been registered');
    }

    public function test_a_lot_admins_form_defaults_to_their_lot(): void
    {
        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(VehicleManager::class)
            ->assertSet('lotId', (string) $this->lotA->id);
    }

    public function test_registering_records_the_lot_and_defaults_to_the_admins_own(): void
    {
        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(VehicleManager::class)
            ->set('rfidId', 'CARD-NEW')
            ->set('vehicleNumber', 'KA01AB1234')
            ->set('driverName', 'A Driver')
            ->set('mobileNumber', '9876543210')
            ->call('add')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vehicles', [
            'rfid_id' => 'CARD-NEW',
            'registered_at_lot_id' => $this->lotA->id,
        ]);
    }

    public function test_a_lot_admin_cannot_attribute_a_registration_to_a_foreign_lot(): void
    {
        // The dropdown only ever offers their own lots, so this is the dishonest-client path.
        $this->actingAsLotAdmin([$this->lotA]);

        Livewire::test(VehicleManager::class)
            ->set('rfidId', 'CARD-SNEAKY')
            ->set('vehicleNumber', 'KA02AB1234')
            ->set('driverName', 'A Driver')
            ->set('mobileNumber', '9876543210')
            ->set('lotId', (string) $this->lotB->id)
            ->call('add')
            ->assertForbidden();

        $this->assertDatabaseMissing('vehicles', ['rfid_id' => 'CARD-SNEAKY']);
    }

    public function test_the_lot_dropdown_only_offers_lots_the_admin_administers(): void
    {
        $this->actingAsLotAdmin([$this->lotA]);

        $lots = Livewire::test(VehicleManager::class)->viewData('lots');

        $this->assertSame([$this->lotA->id], $lots->pluck('id')->all());
    }

    public function test_a_super_admin_may_register_without_attributing_a_lot(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(VehicleManager::class)
            ->set('rfidId', 'CARD-GENERAL')
            ->set('vehicleNumber', 'KA03AB1234')
            ->set('driverName', 'A Driver')
            ->set('mobileNumber', '9876543210')
            ->call('add')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vehicles', ['rfid_id' => 'CARD-GENERAL', 'registered_at_lot_id' => null]);
    }

    public function test_the_registry_stays_global_even_though_the_list_is_scoped(): void
    {
        // The reason for the distinction: scoping must not reintroduce the per-lot registry
        // that let the same card be registered twice.
        $this->vehicle('CARD-SHARED', $this->lotA);

        $this->actingAsLotAdmin([$this->lotB]);

        Livewire::test(VehicleManager::class)
            ->set('rfidId', 'CARD-SHARED')
            ->set('vehicleNumber', 'KA99ZZ9999')
            ->set('driverName', 'Impostor')
            ->set('mobileNumber', '9876543211')
            ->call('add')
            ->assertHasErrors('rfidId');
    }
}
