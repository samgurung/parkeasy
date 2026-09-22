<?php

namespace Tests\Feature;

use App\Models\ParkingFloor;
use App\Models\ParkingLot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingSlotsPageTest extends TestCase
{
    use RefreshDatabase;

    private ?ParkingLot $cachedLot = null;

    private function lot(): ParkingLot
    {
        if ($this->cachedLot === null) {
            // The UI defaults to the first lot by lot_number, which is the
            // seeded "Main Lot" (lot_number 1). Floors must live there so the
            // default dashboard selection shows them.
            $this->cachedLot = ParkingLot::firstOrCreate(
                ['lot_number' => 1],
                ['name' => 'Main Lot'],
            );
        }

        return $this->cachedLot;
    }

    private function makeFloor(int $floorNumber = 0, int $slotCount = 4): ParkingFloor
    {
        $floor = ParkingFloor::create([
            'name'           => "Floor {$floorNumber}",
            'parking_lot_id' => $this->lot()->id,
            'floor_number'   => $floorNumber,
            'slot_count'     => $slotCount,
        ]);

        $floor->syncSlots();

        $floor->setRelation('slots', $floor->slots()->get());

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

    public function test_slot_monitor_lists_lots_and_defaults_to_first_lot(): void
    {
        $this->makeFloor(0, 2);

        $this->get('/slots')
            ->assertOk()
            ->assertSee('Main Lot')
            ->assertSee('Place 1');
    }

    public function test_slot_monitor_filters_floors_by_selected_lot(): void
    {
        $this->makeFloor(0, 2);

        $otherLot = ParkingLot::create([
            'name'       => 'Other Lot',
            'lot_number' => 3606,
        ]);

        $otherFloor = ParkingFloor::create([
            'name'           => 'Other Floor',
            'parking_lot_id' => $otherLot->id,
            'floor_number'   => 0,
            'slot_count'     => 3,
        ]);

        $otherFloor->syncSlots();

        // Both lots are selectable on the page.
        $this->get('/slots')
            ->assertOk()
            ->assertSee('Main Lot')
            ->assertSee('Other Lot')
            ->assertSee('#1 — Main Lot');

        // Selecting the other lot shows only its floors.
        \Livewire\Livewire::test(\App\Livewire\SlotDashboard::class)
            ->set('lotId', $otherLot->id)
            ->call('refresh')
            ->assertSet('lotId', $otherLot->id)
            ->assertSee('Other Floor')
            ->assertViewHas('floors', function ($floors) use ($otherLot) {
                return $floors->count() === 1 && $floors->first()->parking_lot_id === $otherLot->id;
            });
    }

    public function test_lot_report_shows_all_lots_with_occupancy(): void
    {
        $this->makeFloor(0, 3);
        $otherLot = ParkingLot::create([
            'name'       => 'Downtown Lot',
            'lot_number' => 3607,
            'address'    => '12 Market Street',
        ]);

        $otherFloor = ParkingFloor::create([
            'name'           => 'Downtown Floor',
            'parking_lot_id' => $otherLot->id,
            'floor_number'   => 0,
            'slot_count'     => 6,
        ]);
        $otherFloor->syncSlots();
        $otherFloor->slots()->where('slot_number', 1)->update(['is_occupied' => true]);

        $this->get('/lots')
            ->assertOk()
            ->assertSee('Live Lot Report')
            ->assertSee('Main Lot')
            ->assertSee('Downtown Lot')
            ->assertSee('12 Market Street')
            ->assertSee('% full');
    }

    public function test_lot_report_hides_lots_without_floors_or_slots(): void
    {
        // Configured lot with 2 slots (seeded Main Lot has none).
        $this->makeFloor(0, 2);

        // Unconfigured lot with no floors/slots.
        ParkingLot::create([
            'name'       => 'Empty Lot',
            'lot_number' => 3609,
            'address'    => '1 Nowhere Lane',
        ]);

        \Livewire\Livewire::test(\App\Livewire\LotOverview::class)
            ->assertOk()
            ->assertSee('Main Lot')
            ->assertDontSee('Empty Lot')
            ->assertViewHas('lots', fn ($lots) => $lots->every(fn ($lot) => $lot->total_slots > 0));
    }

    public function test_lot_report_searches_by_address(): void
    {
        $plazaLot = ParkingLot::create([
            'name'       => 'Plaza Lot',
            'lot_number' => 3607,
            'address'    => '99 Pine Avenue',
        ]);
        $plazaFloor = ParkingFloor::create([
            'name'           => 'Plaza Floor',
            'parking_lot_id' => $plazaLot->id,
            'floor_number'   => 0,
            'slot_count'     => 2,
        ]);
        $plazaFloor->syncSlots();
        $this->makeFloor(0, 2); // Main Lot, no address

        \Livewire\Livewire::test(\App\Livewire\LotOverview::class)
            ->set('search', 'Pine Avenue')
            ->assertSee('Plaza Lot')
            ->assertViewHas('lots', fn ($lots) => $lots->count() === 1 && $lots->first()->name === 'Plaza Lot');

        \Livewire\Livewire::test(\App\Livewire\LotOverview::class)
            ->set('search', 'zzz-no-match')
            ->assertSee('No parking lots match your search')
            ->assertViewHas('lots', fn ($lots) => $lots->isEmpty());
    }

    public function test_lot_report_sorts_most_free_first(): void
    {
        // Main Lot: 2 slots, 0 occupied → 2 free.
        $this->makeFloor(0, 2);

        // Busy Lot: 4 slots, 3 occupied → 1 free.
        $busyLot = ParkingLot::create([
            'name'       => 'Busy Lot',
            'lot_number' => 3608,
        ]);

        $busyFloor = ParkingFloor::create([
            'name'           => 'Busy Floor',
            'parking_lot_id' => $busyLot->id,
            'floor_number'   => 0,
            'slot_count'     => 4,
        ]);
        $busyFloor->syncSlots();
        $busyFloor->slots()->whereIn('slot_number', [1, 2, 3])->update(['is_occupied' => true]);

        \Livewire\Livewire::test(\App\Livewire\LotOverview::class)
            ->assertViewHas('lots', function ($lots) {
                return $lots->first()->free_slots === 2
                    && $lots->first()->name === 'Main Lot'
                    && $lots->last()->free_slots === 1
                    && $lots->last()->name === 'Busy Lot';
            });
    }
}
