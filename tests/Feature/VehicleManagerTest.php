<?php

namespace Tests\Feature;

use App\Livewire\Admin\VehicleManager;
use App\Models\Vehicle;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithStaff;
use Tests\TestCase;

class VehicleManagerTest extends TestCase
{
    // These cover the existing admin behaviour for a fully-privileged user; the
    // restricted-role cases live in StaffAuthorizationTest.
    use InteractsWithStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpStaff();
        $this->actingAsSuperAdmin();
    }

    public function test_admin_page_lists_registered_vehicles(): void
    {
        Vehicle::create([
            'rfid_id' => 'CARD-A',
            'vehicle_number' => 'KA01AB1234',
            'vehicle_type' => 'four_wheeler',
            'driver_name' => 'John',
            'mobile_number' => '9876543210',
        ]);

        $this->get('/admin/vehicles')
            ->assertOk()
            ->assertSee('CARD-A')
            ->assertSee('KA01AB1234')
            ->assertSee('John');
    }

    public function test_only_the_three_most_recent_vehicles_are_listed(): void
    {
        // Five registered cards: the registry is expected to grow without bound, so the
        // landing view stays a fixed size and search is how an operator reaches the rest.
        foreach (range(1, 5) as $n) {
            Vehicle::create([
                'rfid_id' => 'CARD-'.$n,
                'vehicle_number' => 'KA0'.$n.'AB1234',
                'vehicle_type' => 'four_wheeler',
                'driver_name' => 'Driver '.$n,
                'mobile_number' => '98765432'.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
                // Distinct timestamps, so "most recent" is unambiguous rather than
                // depending on insertion order or id tie-breaking.
                'created_at' => now()->subDays(5 - $n),
            ]);
        }

        Livewire::test(VehicleManager::class)
            ->assertViewHas('vehicles', fn ($vehicles) => $vehicles->count() === 3)
            // Newest three, oldest two absent.
            ->assertSee('CARD-5')
            ->assertSee('CARD-4')
            ->assertSee('CARD-3')
            ->assertDontSee('CARD-2')
            ->assertDontSee('CARD-1')
            ->assertSee('Showing the 3 most recently added');
    }

    public function test_editing_an_older_vehicle_does_not_pull_it_into_the_recent_list(): void
    {
        // One card from a year ago, plus three newer ones. The old card sits outside the
        // recent window, and editing it must not change that: the list answers "what did we
        // just add?", not "what did we just touch?".
        Vehicle::create([
            'rfid_id' => 'CARD-OLD', 'vehicle_number' => 'KA01AB1234',
            'vehicle_type' => 'four_wheeler', 'driver_name' => 'Oldest', 'mobile_number' => '9876543210',
            'created_at' => now()->subYear(),
        ]);

        foreach (range(1, 3) as $n) {
            Vehicle::create([
                'rfid_id' => 'CARD-NEW'.$n,
                'vehicle_number' => 'KA1'.$n.'AB1234',
                'vehicle_type' => 'four_wheeler',
                'driver_name' => 'New '.$n,
                'mobile_number' => '91234532'.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
                'created_at' => now()->subDays(4 - $n),
            ]);
        }

        $oldId = Vehicle::where('rfid_id', 'CARD-OLD')->value('id');

        Livewire::test(VehicleManager::class)
            ->assertDontSee('CARD-OLD')
            ->set('editingId', $oldId)
            ->set('editRfidId', 'CARD-OLD')
            ->set('editVehicleNumber', 'KA01ZZ9999')
            ->set('editDriverName', 'Oldest')
            ->set('editMobileNumber', '9876543210')
            ->call('save')
            // The recent list still shows the three newer cards.
            ->assertSee('CARD-NEW3')
            ->assertDontSee('KA01ZZ9999');

        // ...and the rename itself did land on the vehicle.
        $this->assertDatabaseHas('vehicles', ['id' => $oldId, 'vehicle_number' => 'KA01ZZ9999']);
    }

    public function test_search_reaches_a_vehicle_that_is_not_in_the_recent_list(): void
    {
        foreach (range(1, 4) as $n) {
            Vehicle::create([
                'rfid_id' => 'CARD-'.$n,
                'vehicle_number' => 'KA0'.$n.'AB1234',
                'vehicle_type' => 'four_wheeler',
                'driver_name' => 'Driver '.$n,
                'mobile_number' => '98765432'.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
                'created_at' => now()->subDays(5 - $n),
            ]);
        }

        // CARD-1 is the oldest, so it never appears unsearched - but it is still findable.
        Livewire::test(VehicleManager::class)
            ->assertDontSee('CARD-1')
            ->set('search', 'CARD-1')
            ->assertSee('CARD-1')
            ->assertDontSee('CARD-2')
            ->assertSee('Matches')
            ->assertSee('(1 of 4)');
    }

    public function test_a_whitespace_only_search_is_treated_as_no_search(): void
    {
        foreach (range(1, 4) as $n) {
            Vehicle::create([
                'rfid_id' => 'CARD-'.$n,
                'vehicle_number' => 'KA0'.$n.'AB1234',
                'vehicle_type' => 'four_wheeler',
                'driver_name' => 'Driver '.$n,
                'mobile_number' => '98765432'.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
                'created_at' => now()->subDays(5 - $n),
            ]);
        }

        // A stray space in the search box should not turn a recent-vehicles view into a
        // search that matches nothing.
        Livewire::test(VehicleManager::class)
            ->set('search', '   ')
            ->assertSee('Recently Added')
            ->assertViewHas('searching', false)
            ->assertSee('CARD-4');
    }

    public function test_a_very_broad_search_says_when_results_are_capped(): void
    {
        // A one-character term matches nearly everything, which is how an unbounded list
        // would come back through the search box. The cap is announced, not silent.
        foreach (range(1, 30) as $n) {
            Vehicle::create([
                'rfid_id' => 'CARD-'.$n,
                'vehicle_number' => 'KA'.$n.'AB1234',
                'vehicle_type' => 'four_wheeler',
                'driver_name' => 'Driver '.$n,
                'mobile_number' => '98765432'.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
            ]);
        }

        Livewire::test(VehicleManager::class)
            ->set('search', 'Driver')
            ->assertViewHas('vehicles', fn ($vehicles) => $vehicles->count() === 25)
            ->assertSee('Showing the first 25 of 30 matches');
    }

    public function test_a_vehicle_and_card_can_be_registered(): void
    {
        Livewire::test(VehicleManager::class)
            ->set('rfidId', 'card-a')
            ->set('vehicleNumber', 'ka01ab1234')
            ->set('driverName', 'John')
            ->set('mobileNumber', '9876543210')
            ->set('vehicleType', 'four_wheeler')
            ->call('add')
            ->assertHasNoErrors();

        // Codes are normalised so a card scans the same way it is registered.
        $this->assertDatabaseHas('vehicles', [
            'rfid_id' => 'CARD-A',
            'vehicle_number' => 'KA01AB1234',
            'driver_name' => 'John',
            'mobile_number' => '9876543210',
        ]);
    }

    public function test_a_card_cannot_be_registered_twice(): void
    {
        Vehicle::create([
            'rfid_id' => 'CARD-A',
            'vehicle_number' => 'KA01AB1234',
            'vehicle_type' => 'four_wheeler',
            'driver_name' => 'John',
            'mobile_number' => '9876543210',
        ]);

        Livewire::test(VehicleManager::class)
            ->set('rfidId', 'CARD-A')
            ->set('vehicleNumber', 'KA02CD5678')
            ->set('driverName', 'Jane')
            ->set('mobileNumber', '9123456780')
            ->call('add')
            ->assertHasErrors(['rfidId' => 'unique']);

        $this->assertDatabaseCount('vehicles', 1);
    }

    public function test_registration_requires_a_full_ten_digit_mobile_number(): void
    {
        Livewire::test(VehicleManager::class)
            ->set('rfidId', 'CARD-B')
            ->set('vehicleNumber', 'KA01AB1234')
            ->set('driverName', 'John')
            ->set('mobileNumber', '12345')
            ->call('add')
            ->assertHasErrors(['mobileNumber']);

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_registration_requires_a_known_vehicle_type(): void
    {
        Livewire::test(VehicleManager::class)
            ->set('rfidId', 'CARD-C')
            ->set('vehicleNumber', 'KA01AB1234')
            ->set('driverName', 'John')
            ->set('mobileNumber', '9876543210')
            ->set('vehicleType', 'spaceship')
            ->call('add')
            ->assertHasErrors(['vehicleType']);
    }

    public function test_a_vehicle_can_be_edited(): void
    {
        $vehicle = Vehicle::create([
            'rfid_id' => 'CARD-A',
            'vehicle_number' => 'KA01AB1234',
            'vehicle_type' => 'four_wheeler',
            'driver_name' => 'John',
            'mobile_number' => '9876543210',
        ]);

        Livewire::test(VehicleManager::class)
            ->call('edit', $vehicle->id)
            ->set('editDriverName', 'Jane')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Jane', $vehicle->fresh()->driver_name);
    }

    public function test_rekeying_a_card_follows_through_to_its_visits(): void
    {
        $vehicle = Vehicle::create([
            'rfid_id' => 'CARD-A',
            'vehicle_number' => 'KA01AB1234',
            'vehicle_type' => 'four_wheeler',
            'driver_name' => 'John',
            'mobile_number' => '9876543210',
        ]);

        $entry = $vehicle->entries()->create([
            'rfid_id' => 'CARD-A',
            'entry_time' => now(),
            'status' => 'exited',
            'exit_time' => now(),
        ]);

        Livewire::test(VehicleManager::class)
            ->call('edit', $vehicle->id)
            ->set('editRfidId', 'CARD-Z')
            ->call('save')
            ->assertHasNoErrors();

        // Otherwise the visit could no longer be matched to the vehicle it belongs to.
        $this->assertSame('CARD-Z', $entry->fresh()->rfid_id);
    }

    public function test_deleting_a_vehicle_keeps_its_visit_history(): void
    {
        $vehicle = Vehicle::create([
            'rfid_id' => 'CARD-A',
            'vehicle_number' => 'KA01AB1234',
            'vehicle_type' => 'four_wheeler',
            'driver_name' => 'John',
            'mobile_number' => '9876543210',
        ]);

        $entry = $vehicle->entries()->create([
            'rfid_id' => 'CARD-A',
            'entry_time' => now(),
            'status' => 'exited',
            'exit_time' => now(),
        ]);

        Livewire::test(VehicleManager::class)
            ->call('confirmDelete', $vehicle->id)
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);

        // The record survives for auditing, just no longer tied to a vehicle.
        $this->assertNull($entry->fresh()->vehicle_id);
    }

    public function test_the_list_can_be_searched(): void
    {
        Vehicle::create([
            'rfid_id' => 'CARD-A', 'vehicle_number' => 'KA01AB1234',
            'vehicle_type' => 'four_wheeler', 'driver_name' => 'John', 'mobile_number' => '9876543210',
        ]);
        Vehicle::create([
            'rfid_id' => 'CARD-B', 'vehicle_number' => 'KA02CD5678',
            'vehicle_type' => 'two_wheeler', 'driver_name' => 'Jane', 'mobile_number' => '9123456780',
        ]);

        Livewire::test(VehicleManager::class)
            ->set('search', 'Jane')
            ->assertSee('CARD-B')
            ->assertDontSee('CARD-A');
    }
}
