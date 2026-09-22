<?php

namespace Tests\Feature;

use App\Events\SlotStatusChanged;
use App\Models\ParkingFloor;
use App\Models\ParkingLot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SlotStatusApiTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_LOT  = 101;
    private const TEST_LOT2 = 202;

    private function makeFloor(int $floorNumber = 0, int $slotCount = 4): ParkingFloor
    {
        $floor = ParkingFloor::create([
            'name'           => "Floor {$floorNumber}",
            'parking_lot_id' => ParkingLot::where('lot_number', self::TEST_LOT)->value('id'),
            'floor_number'   => $floorNumber,
            'slot_count'     => $slotCount,
        ]);

        $floor->syncSlots();

        return $floor;
    }

    private function makeLot(int $lotNumber = self::TEST_LOT, int $floorCount = 0, int $slotCount = 4): ParkingLot
    {
        $lot = ParkingLot::create([
            'name'       => "Lot {$lotNumber}",
            'lot_number' => $lotNumber,
        ]);

        for ($i = 0; $i < $floorCount; $i++) {
            $floor = ParkingFloor::create([
                'name'           => "Floor {$i}",
                'parking_lot_id' => $lot->id,
                'floor_number'   => $i,
                'slot_count'     => $slotCount,
            ]);

            $floor->syncSlots();

            $lot->setRelation('floors', $lot->floors()->get());
        }

        return $lot;
    }

    private function hitApi(int $lot, int $floor, int $slot): TestResponse
    {
        return $this->postJson('/api/slot-status', [
            'lot'   => $lot,
            'floor' => $floor,
            'slot'  => $slot,
        ]);
    }

    public function test_first_hit_marks_slot_occupied(): void
    {
        $this->makeLot(self::TEST_LOT, 1, 4);

        $this->hitApi(self::TEST_LOT, 0, 3)->assertOk()->assertJson([
            'success' => true,
            'lot'     => self::TEST_LOT,
            'floor'   => 0,
            'slot'    => 3,
            'status'  => 'occupied',
            'is_occupied' => true,
        ]);
    }

    public function test_second_hit_marks_slot_free(): void
    {
        $this->makeLot(self::TEST_LOT, 1, 4);

        $this->hitApi(self::TEST_LOT, 0, 2)->assertOk()->assertJson([
            'status' => 'occupied',
            'is_occupied' => true,
        ]);

        $this->hitApi(self::TEST_LOT, 0, 2)->assertOk()->assertJson([
            'status' => 'free',
            'is_occupied' => false,
        ]);
    }

    public function test_third_hit_toggles_slot_back_to_occupied(): void
    {
        $this->makeLot(self::TEST_LOT, 1, 4);

        $this->hitApi(self::TEST_LOT, 0, 1);
        $this->hitApi(self::TEST_LOT, 0, 1);

        $this->hitApi(self::TEST_LOT, 0, 1)->assertOk()->assertJson([
            'status' => 'occupied',
            'is_occupied' => true,
        ]);
    }

    public function test_slots_toggle_independently(): void
    {
        $this->makeLot(self::TEST_LOT, 1, 4);

        $this->hitApi(self::TEST_LOT, 0, 1);
        $this->hitApi(self::TEST_LOT, 0, 2);

        $this->hitApi(self::TEST_LOT, 0, 1)->assertOk()->assertJson(['is_occupied' => false]);
        $this->hitApi(self::TEST_LOT, 0, 2)->assertOk()->assertJson(['is_occupied' => false]);

        $this->hitApi(self::TEST_LOT, 0, 1)->assertOk()->assertJson(['is_occupied' => true]);
    }

    public function test_lots_toggle_independently(): void
    {
        $this->makeLot(self::TEST_LOT, 1, 4);
        $this->makeLot(self::TEST_LOT2, 1, 4);

        $this->hitApi(self::TEST_LOT, 0, 1)->assertOk()->assertJson(['is_occupied' => true]);
        $this->hitApi(self::TEST_LOT2, 0, 1)->assertOk()->assertJson(['is_occupied' => true]);

        $this->hitApi(self::TEST_LOT, 0, 1)->assertOk()->assertJson(['is_occupied' => false]);
        $this->hitApi(self::TEST_LOT2, 0, 1)->assertOk()->assertJson(['is_occupied' => false]);
    }

    public function test_unknown_lot_returns_404(): void
    {
        $this->makeLot(self::TEST_LOT, 1, 4);

        $this->hitApi(9999, 0, 1)->assertNotFound();
    }

    public function test_unknown_floor_returns_404(): void
    {
        $this->makeLot(self::TEST_LOT, 1, 4);

        $this->hitApi(self::TEST_LOT, 99, 1)->assertNotFound();
    }

    public function test_slot_exceeding_count_returns_404(): void
    {
        $this->makeLot(self::TEST_LOT, 1, 3);

        $this->hitApi(self::TEST_LOT, 0, 99)->assertNotFound();
    }

    public function test_validates_required_fields(): void
    {
        $this->postJson('/api/slot-status', [])->assertUnprocessable();
        $this->postJson('/api/slot-status', ['floor' => 0])->assertUnprocessable();
        $this->postJson('/api/slot-status', ['slot' => 2])->assertUnprocessable();
    }

    public function test_ignores_status_field(): void
    {
        $this->makeLot(self::TEST_LOT, 1, 4);

        $response = $this->postJson('/api/slot-status', [
            'lot'    => self::TEST_LOT,
            'floor'  => 0,
            'slot'   => 2,
            'status' => 'occupied',
        ])->assertOk()->assertJson(['status' => 'occupied', 'is_occupied' => true]);

        $this->postJson('/api/slot-status', [
            'lot'    => self::TEST_LOT,
            'floor'  => 0,
            'slot'   => 2,
            'status' => 'free',
        ])->assertOk()->assertJson(['status' => 'free', 'is_occupied' => false]);
    }

    public function test_broadcasts_slot_status_change(): void
    {
        Event::fake([SlotStatusChanged::class]);

        $this->makeLot(self::TEST_LOT, 1, 4);

        $this->hitApi(self::TEST_LOT, 0, 1)->assertOk();

        Event::assertDispatched(SlotStatusChanged::class, function (SlotStatusChanged $event): bool {
            return $event->lotNumber === self::TEST_LOT
                && $event->floorNumber === 0
                && $event->slotNumber === 1
                && $event->isOccupied === true;
        });
    }
}
