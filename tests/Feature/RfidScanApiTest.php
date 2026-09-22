<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\Kiosk;
use App\Models\ParkingLot;
use App\Models\Vehicle;
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

    private function makeVehicle(string $rfid = 'CARD-A'): Vehicle
    {
        return Vehicle::create([
            'name' => 'Owner',
            'phone' => '1234567890',
            'rfid_id' => $rfid,
        ]);
    }

    private function parkVehicle(Vehicle $vehicle, ParkingLot $lot, string $vehicleNumber = 'KA01AB1234'): Entry
    {
        return $vehicle->entries()->create([
            'entry_time' => now(),
            'driver_name' => 'Owner',
            'vehicle_number' => $vehicleNumber,
            'mobile_number' => '1234567890',
            'status' => 'parked',
            'parking_lot_id' => $lot->id,
        ]);
    }

    private function scan(string $rfid, string $type, int $lot): TestResponse
    {
        return $this->postJson('/api/rfid-scan', [
            'rfid_id' => $rfid,
            'type' => $type,
            'lot' => $lot,
        ]);
    }

    public function test_lot_is_required_on_scan(): void
    {
        $this->makeLot();

        $this->postJson('/api/rfid-scan', ['rfid_id' => 'X', 'type' => 'entry'])
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

    public function test_unregistered_entry_scan_reports_the_lot(): void
    {
        $this->makeLot(self::LOT_A);

        $this->scan('CARD-A', 'entry', self::LOT_A)
            ->assertNotFound()
            ->assertJson([
                'status' => 'unregistered',
                'rfid_id' => 'CARD-A',
                'lot' => self::LOT_A,
            ]);
    }

    public function test_entry_scan_returns_details_required_with_lot(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $this->makeVehicle();

        $this->scan('CARD-A', 'entry', self::LOT_A)
            ->assertOk()
            ->assertJson([
                'status' => 'details_required',
                'lot' => self::LOT_A,
                'lot_name' => $lot->name,
            ]);
    }

    public function test_register_stores_the_entry_against_the_lot(): void
    {
        $lot = $this->makeLot(self::LOT_A);

        $response = $this->postJson('/api/rfid-scan/register', [
            'rfid_id' => 'CARD-A',
            'name' => 'John',
            'phone' => '9876543210',
            'vehicle_number' => 'ka01ab1234',
            'lot' => self::LOT_A,
        ])->assertOk()
            ->assertJson(['status' => 'parked', 'lot' => self::LOT_A, 'lot_name' => $lot->name]);

        $entry = Entry::findOrFail($response->json('entry_id'));

        $this->assertSame($lot->id, $entry->parking_lot_id);
        $this->assertSame('KA01AB1234', $entry->vehicle_number);
    }

    public function test_save_details_stores_the_entry_against_the_lot(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $this->makeVehicle();

        $this->scan('CARD-A', 'entry', self::LOT_A)->assertOk();

        $response = $this->postJson('/api/rfid-scan/details', [
            'rfid_id' => 'CARD-A',
            'driver_name' => 'John',
            'vehicle_number' => 'KA01AB1234',
            'mobile_number' => '9876543210',
            'lot' => self::LOT_A,
        ])->assertOk()->assertJson(['status' => 'parked']);

        $entry = Entry::findOrFail($response->json('entry_id'));

        $this->assertSame($lot->id, $entry->parking_lot_id);
    }

    public function test_register_and_details_require_lot(): void
    {
        $this->makeLot(self::LOT_A);
        $this->makeVehicle();

        $this->postJson('/api/rfid-scan/register', [
            'rfid_id' => 'CARD-B',
            'name' => 'John',
            'phone' => '9876543210',
            'vehicle_number' => 'KA01AB1234',
        ])->assertUnprocessable()->assertJsonValidationErrors('lot');

        $this->postJson('/api/rfid-scan/details', [
            'rfid_id' => 'CARD-A',
            'driver_name' => 'John',
            'vehicle_number' => 'KA01AB1234',
            'mobile_number' => '9876543210',
        ])->assertUnprocessable()->assertJsonValidationErrors('lot');
    }

    public function test_exit_at_the_same_lot_closes_the_entry(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $vehicle = $this->makeVehicle();
        $entry = $this->parkVehicle($vehicle, $lot);

        $this->scan('CARD-A', 'exit', self::LOT_A)
            ->assertOk()
            ->assertJson(['status' => 'exit', 'entry_id' => $entry->id, 'amount' => 20]);

        $this->assertSame('exited', $entry->fresh()->status);
        $this->assertNotNull($entry->fresh()->exit_time);
    }

    public function test_exit_at_a_different_lot_is_rejected(): void
    {
        $lotA = $this->makeLot(self::LOT_A);
        $lotB = $this->makeLot(self::LOT_B);
        $vehicle = $this->makeVehicle();
        $entry = $this->parkVehicle($vehicle, $lotA);

        $this->scan('CARD-A', 'exit', self::LOT_B)
            ->assertUnprocessable()
            ->assertJson(['error' => 'Vehicle is parked at a different lot']);

        $this->assertSame('parked', $entry->fresh()->status);
        $this->assertNull($entry->fresh()->exit_time);
    }

    public function test_old_entries_without_a_lot_can_still_exit(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        $vehicle = $this->makeVehicle();
        $entry = $vehicle->entries()->create([
            'entry_time' => now(),
            'driver_name' => 'Owner',
            'vehicle_number' => 'KA01AB1234',
            'mobile_number' => '1234567890',
            'status' => 'parked',
            'parking_lot_id' => null,
        ]);

        $this->scan('CARD-A', 'exit', self::LOT_A)
            ->assertOk()
            ->assertJson(['status' => 'exit', 'entry_id' => $entry->id]);

        $this->assertSame('exited', $entry->fresh()->status);
    }

    public function test_kiosk_page_resolves_its_lot_from_the_database(): void
    {
        $lot = $this->makeLot(self::LOT_A);
        Kiosk::create([
            'name' => 'Main Gate',
            'key' => 'main-gate',
            'parking_lot_id' => $lot->id,
        ]);

        $this->get('/?kiosk=main-gate')
            ->assertOk()
            ->assertSee('Main Gate')
            ->assertSee('PARKING LOT #' . self::LOT_A);
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
        Kiosk::create([
            'name' => 'Main Gate',
            'key' => 'main-gate',
            'parking_lot_id' => $lot->id,
        ]);
        Kiosk::where('key', 'main-gate')->update(['parking_lot_id' => null]);

        $this->postJson('/api/rfid-scan', [
            'rfid_id' => 'CARD-A',
            'type' => 'entry',
            'lot' => self::LOT_A,
            'kiosk' => 'main-gate',
        ])->assertUnprocessable()
            ->assertJson(['error' => 'Kiosk is not linked to a parking lot']);
    }

    public function test_kiosk_key_for_a_different_lot_is_refused(): void
    {
        $this->makeLot(self::LOT_A);
        $lotB = $this->makeLot(self::LOT_B);
        Kiosk::create([
            'name' => 'Entry Gate',
            'key' => 'entry-gate',
            'parking_lot_id' => $lotB->id,
        ]);

        $this->postJson('/api/rfid-scan', [
            'rfid_id' => 'CARD-A',
            'type' => 'entry',
            'lot' => self::LOT_A,
            'kiosk' => 'entry-gate',
        ])->assertUnprocessable()
            ->assertJson(['error' => 'Kiosk is not linked to this parking lot']);
    }
}
