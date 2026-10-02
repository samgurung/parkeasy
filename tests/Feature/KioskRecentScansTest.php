<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\Kiosk;
use App\Models\ParkingLot;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KioskRecentScansTest extends TestCase
{
    use RefreshDatabase;

    private const LOT_NUMBER = 701;

    private ParkingLot $lot;

    private ParkingLot $otherLot;

    private Kiosk $entryKiosk;

    private Kiosk $exitKiosk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lot = ParkingLot::create([
            'name' => 'Test Lot',
            'lot_number' => self::LOT_NUMBER,
        ]);

        $this->otherLot = ParkingLot::create([
            'name' => 'Other Lot',
            'lot_number' => 702,
        ]);

        // A lot has a separate entry and exit kiosk; both belong to the same lot.
        $this->entryKiosk = Kiosk::create([
            'name' => 'Test Entry',
            'key' => 'test-entry',
            'type' => Kiosk::TYPE_ENTRY,
            'parking_lot_id' => $this->lot->id,
        ]);

        $this->exitKiosk = Kiosk::create([
            'name' => 'Test Exit',
            'key' => 'test-exit',
            'type' => Kiosk::TYPE_EXIT,
            'parking_lot_id' => $this->lot->id,
        ]);
    }

    /**
     * Load the kiosk page tied to the given kiosk and return the "recent scans" rows
     * as card codes paired with their status.
     *
     * @return array<int, array{card: string, status: string, kiosk: ?string}>
     */
    private function recentScansFor(Kiosk $kiosk): array
    {
        $response = $this->get('/?kiosk='.$kiosk->key);
        $response->assertOk();

        preg_match_all(
            '/data-card-code="([^"]+)"(?:\s+data-scan-status="([^"]+)")?(?:\s+data-kiosk="([^"]*)")?/',
            $response->getContent(),
            $matches,
            PREG_SET_ORDER,
        );

        return array_map(fn (array $m) => [
            'card' => $m[1],
            'status' => $m[2] ?? '',
            'kiosk' => $m[3] ?? null,
        ], $matches);
    }

    /**
     * @param  array<int, array{card: string, status: string, kiosk: ?string}>  $rows
     * @return array<int, string>
     */
    private function cards(array $rows): array
    {
        return array_column($rows, 'card');
    }

    private function makeVehicle(string $rfid): Vehicle
    {
        return Vehicle::create([
            'rfid_id' => $rfid,
            'vehicle_number' => strtoupper($rfid),
            'vehicle_type' => 'four_wheeler',
            'driver_name' => 'Owner of '.$rfid,
            'mobile_number' => '9876543210',
        ]);
    }

    private function admit(Vehicle $vehicle, ?ParkingLot $lot = null, ?string $kioskKey = 'test-entry'): Entry
    {
        return Entry::create([
            'rfid_id' => $vehicle->rfid_id,
            'vehicle_id' => $vehicle->id,
            'entry_time' => now(),
            'status' => 'parked',
            'parking_lot_id' => ($lot ?? $this->lot)->id,
            'entry_kiosk_key' => $kioskKey,
        ]);
    }

    public function test_a_kiosk_sees_scans_from_the_sibling_kiosk_at_the_same_lot(): void
    {
        // Admitted on the entry kiosk...
        $admitted = $this->admit($this->makeVehicle('CARD-A'), $this->lot, 'test-entry');
        // ...and released on the exit kiosk.
        $admitted->update(['status' => 'exited', 'exit_time' => now(), 'exit_kiosk_key' => 'test-exit']);

        // Both kiosks at the lot need the full picture: the attendant has to be able to
        // see that the vehicle came in and went out.
        foreach ([$this->entryKiosk, $this->exitKiosk] as $kiosk) {
            $cards = $this->cards($this->recentScansFor($kiosk));

            $this->assertSame(
                ['CARD-A', 'CARD-A'],
                $cards,
                'The feed covers the whole lot, so a visit appears as both a parked and an exit event.',
            );
        }
    }

    public function test_rows_are_tagged_with_the_kiosk_that_handled_them(): void
    {
        $entry = $this->admit($this->makeVehicle('CARD-A'), $this->lot, 'test-entry');
        $entry->update(['status' => 'exited', 'exit_time' => now(), 'exit_kiosk_key' => 'test-exit']);

        $rows = $this->recentScansFor($this->entryKiosk);

        $byStatus = [];
        foreach ($rows as $row) {
            $byStatus[$row['status']] = $row['kiosk'];
        }

        $this->assertSame('test-entry', $byStatus['parked'] ?? null);
        $this->assertSame('test-exit', $byStatus['exit'] ?? null);
    }

    public function test_kiosks_at_different_lots_never_share_a_feed(): void
    {
        $this->admit($this->makeVehicle('CARD-MINE'), $this->lot, 'test-entry');

        $otherKiosk = Kiosk::create([
            'name' => 'Other Entry',
            'key' => 'other-entry',
            'type' => Kiosk::TYPE_ENTRY,
            'parking_lot_id' => $this->otherLot->id,
        ]);
        $this->admit($this->makeVehicle('CARD-OTHER-LOT'), $this->otherLot, 'other-entry');

        $cards = $this->cards($this->recentScansFor($this->entryKiosk));

        $this->assertContains('CARD-MINE', $cards);
        $this->assertNotContains('CARD-OTHER-LOT', $cards, 'Another lot\'s scans must never leak into this feed.');
    }

    public function test_an_unlinked_kiosk_shows_no_scans(): void
    {
        $unlinked = Kiosk::create(['name' => 'Unlinked', 'key' => 'unlinked']);
        $this->admit($this->makeVehicle('CARD-MINE'));

        $this->assertSame([], $this->recentScansFor($unlinked));
    }

    public function test_a_visit_with_no_kiosk_recorded_still_lists(): void
    {
        // Rows created before kiosks were attributed have no key; they must still show.
        $this->admit($this->makeVehicle('CARD-OLD'), $this->lot, null);

        $rows = $this->recentScansFor($this->entryKiosk);

        $this->assertContains('CARD-OLD', $this->cards($rows));
    }

    public function test_the_kiosk_key_persists_in_the_session_across_navigation(): void
    {
        // The kiosk is keyed by query string, then remembered so / and other pages agree.
        $this->get('/?kiosk=test-entry');

        $this->assertSame('test-entry', session('kiosk_key'));
    }
}
