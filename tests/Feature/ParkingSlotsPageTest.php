<?php

namespace Tests\Feature;

use App\Models\ParkingFloor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingSlotsPageTest extends TestCase
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

        return $floor;
    }

    public function test_dashboard_shows_empty_state_with_no_floors(): void
    {
        $this->get('/slots')
            ->assertOk()
            ->assertSee('Slot Monitor')
            ->assertSee('No floors configured');
    }

    public function test_dashboard_renders_floor_schematic(): void
    {
        $this->makeFloor(0, 3);
        $floor = $this->makeFloor(1, 2);
        $floor->slots()->where('slot_number', 1)->update(['is_occupied' => true]);

        $this->get('/slots')
            ->assertOk()
            ->assertSee('Slot Monitor')
            ->assertSee('Floor 1')
            ->assertSee('Place 1')
            ->assertSee('Place 3')
            ->assertSee('Occupied');
    }

    public function test_dashboard_reports_summary_counts(): void
    {
        $floor = $this->makeFloor(0, 4);
        $floor->slots()->whereIn('slot_number', [1, 2])->update(['is_occupied' => true]);

        $this->get('/slots')
            ->assertOk()
            ->assertSee('id="stat-total"', false)
            ->assertSee('id="stat-occupied"', false)
            ->assertSee('id="stat-free"', false);
    }

    public function test_admin_floors_page_renders_configuration(): void
    {
        $this->makeFloor(0, 5);

        $this->get('/admin/floors')
            ->assertOk()
            ->assertSee('Add New Floor')
            ->assertSee('Floor 0');
    }
}
