<?php

namespace Tests\Feature;

use App\Events\SlotStatusChanged;
use App\Models\ParkingFloor;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SlotStatusApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeFloor(int $floorNumber = 0, int $slotCount = 4): ParkingFloor
    {
        $floor = ParkingFloor::create([
            'name' => "Floor {$floorNumber}",
            'floor_number' => $floorNumber,
            'slot_count' => $slotCount,
        ]);

        $floor->syncSlots();

        $floor->setRelation('slots', $floor->slots()->get());

        return $floor;
    }

    private function hitApi(int $floor, int $slot): TestResponse
    {
        return $this->postJson('/api/slot-status', [
            'floor' => $floor,
            'slot' => $slot,
        ]);
    }

    public function test_first_hit_marks_slot_occupied(): void
    {
        $floor = $this->makeFloor();

        $this->hitApi(0, 3)->assertOk()->assertJson([
            'success' => true,
            'floor' => 0,
            'slot' => 3,
            'status' => 'occupied',
            'is_occupied' => true,
        ]);

        $this->assertTrue($floor->slots()->where('slot_number', 3)->first()->is_occupied);
    }

    public function test_second_hit_toggles_slot_free(): void
    {
        $floor = $this->makeFloor();
        $this->hitApi(0, 2);

        $this->hitApi(0, 2)->assertOk()->assertJson([
            'success' => true,
            'status' => 'free',
            'is_occupied' => false,
        ]);

        $this->assertFalse($floor->slots()->where('slot_number', 2)->first()->is_occupied);
    }

    public function test_third_hit_toggles_slot_back_to_occupied(): void
    {
        $this->makeFloor();

        $this->hitApi(0, 1);
        $this->hitApi(0, 1);
        $this->hitApi(0, 1)->assertOk()->assertJson([
            'status' => 'occupied',
            'is_occupied' => true,
        ]);
    }

    public function test_hits_toggle_independently_per_slot(): void
    {
        $this->makeFloor();

        $this->hitApi(0, 1);
        $this->hitApi(0, 2);

        $this->hitApi(0, 1)->assertOk()->assertJson([
            'slot' => 1,
            'status' => 'free',
            'is_occupied' => false,
        ]);

        $this->hitApi(0, 2)->assertOk()->assertJson([
            'slot' => 2,
            'status' => 'free',
            'is_occupied' => false,
        ]);

        $this->hitApi(0, 1)->assertOk()->assertJson([
            'slot' => 1,
            'status' => 'occupied',
            'is_occupied' => true,
        ]);
    }

    public function test_reports_404_for_unknown_floor(): void
    {
        $this->hitApi(9, 1)->assertNotFound();
    }

    public function test_reports_404_when_slot_exceeds_configured_count(): void
    {
        $this->makeFloor(0, 3);

        $this->hitApi(0, 99)->assertNotFound();
    }

    public function test_validates_required_fields(): void
    {
        $this->makeFloor();

        $this->postJson('/api/slot-status', [])->assertUnprocessable();
        $this->postJson('/api/slot-status', ['floor' => 0])->assertUnprocessable();
        $this->postJson('/api/slot-status', ['slot' => 2])->assertUnprocessable();
    }

    public function test_ignores_any_transmitted_status(): void
    {
        $this->makeFloor();

        // A status field in the payload is ignored: the slot toggles regardless.
        $this->postJson('/api/slot-status', [
            'floor' => 0,
            'slot' => 2,
            'status' => 'occupied',
        ])->assertOk()->assertJson(['status' => 'occupied', 'is_occupied' => true]);

        $this->postJson('/api/slot-status', [
            'floor' => 0,
            'slot' => 2,
            'status' => 'free',
        ])->assertOk()->assertJson(['status' => 'free', 'is_occupied' => false]);
    }

    public function test_broadcasts_slot_status_change(): void
    {
        $recorder = new class extends Broadcaster
        {
            public array $broadcasts = [];

            public function auth($request)
            {
                return true;
            }

            public function validAuthenticationResponse($request, $result)
            {
                return $result;
            }

            public function broadcast(array $channels, $event, array $payload = []): void
            {
                $this->broadcasts[] = [$channels, $event, $payload];
            }
        };

        Broadcast::extend('fake-test', fn () => $recorder);
        config()->set('broadcasting.default', 'fake-test');
        config()->set('broadcasting.connections.fake-test', ['driver' => 'fake-test']);

        $this->makeFloor();

        $this->hitApi(0, 1)->assertOk();

        $this->assertCount(1, $recorder->broadcasts);

        [$channels, $event, $payload] = $recorder->broadcasts[0];

        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertSame('parking-slots', $channels[0]->name);
        $this->assertSame(SlotStatusChanged::class, $event);
        $this->assertSame(0, $payload['floor_number']);
        $this->assertSame(1, $payload['slot_number']);
        $this->assertSame('Floor 0', $payload['floor_name']);
        $this->assertTrue($payload['is_occupied']);
    }
}
