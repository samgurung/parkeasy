<?php

namespace Tests\Feature;

use App\Livewire\Admin\FloorManager;
use App\Livewire\LotOverview;
use App\Livewire\SlotDashboard;
use App\Models\ParkingFloor;
use App\Models\ParkingLot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
            'name' => "Floor {$floorNumber}",
            'parking_lot_id' => $this->lot()->id,
            'floor_number' => $floorNumber,
            'slot_count' => $slotCount,
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

    public function test_admin_floors_page_scopes_floors_to_a_lot(): void
    {
        $this->makeFloor(0, 2); // Main Lot floor, should be hidden when scoped.

        $otherLot = ParkingLot::create([
            'name' => 'Tower Lot',
            'lot_number' => 3610,
        ]);

        $otherFloor = ParkingFloor::create([
            'name' => 'Tower Floor',
            'parking_lot_id' => $otherLot->id,
            'floor_number' => 0,
            'slot_count' => 3,
        ]);
        $otherFloor->syncSlots();

        $this->get('/admin/floors?lot='.$otherLot->id)
            ->assertOk()
            ->assertSee('Tower Floor')
            ->assertSee("Lot #{$otherLot->lot_number}")
            ->assertDontSee('Floor 0');
    }

    public function test_admin_floors_page_lists_all_when_not_scoped(): void
    {
        $this->makeFloor(0, 2);

        $otherLot = ParkingLot::create([
            'name' => 'Tower Lot',
            'lot_number' => 3611,
        ]);

        ParkingFloor::create([
            'name' => 'Tower Floor',
            'parking_lot_id' => $otherLot->id,
            'floor_number' => 0,
            'slot_count' => 3,
        ])->syncSlots();

        $this->get('/admin/floors')
            ->assertOk()
            ->assertSee('Floor 0')
            ->assertSee('Tower Floor');
    }

    public function test_lot_manager_links_each_lot_to_its_floors(): void
    {
        $this->makeFloor(0, 2);

        $this->get('/admin/lots')
            ->assertOk()
            ->assertSee('Floors & Slots')
            ->assertSee(route('admin.floors', ['lot' => $this->lot()->id]));
    }

    public function test_floor_manager_keeps_lot_selected_after_adding_a_floor(): void
    {
        $lot = $this->lot();

        Livewire::test(FloorManager::class)
            ->set('lotId', $lot->id)
            ->set('name', 'New Floor')
            ->set('slotCount', '4')
            ->call('addFloor')
            ->assertSet('lotId', $lot->id)
            ->assertHasNoErrors();
    }

    public function test_floor_manager_stages_toggle_without_persisting(): void
    {
        $floor = $this->makeFloor(0, 6);
        $slot = $floor->slots()->where('slot_number', 4)->firstOrFail();

        Livewire::test(FloorManager::class)
            ->call('toggleSlotType', $slot->id)
            ->assertSet('pendingTypeChanges.'.$slot->id, 'two_wheeler');

        // Nothing persisted until the confirm bar is used.
        $this->assertDatabaseHas('parking_slots', [
            'id' => $slot->id,
            'vehicle_type' => 'four_wheeler',
        ]);
    }

    public function test_floor_manager_confirm_applies_staged_type_changes(): void
    {
        $floor = $this->makeFloor(0, 6);

        // Designate a mid-row and an end slot as two-wheelers, not the first ones.
        $slot3 = $floor->slots()->where('slot_number', 3)->firstOrFail();
        $slot6 = $floor->slots()->where('slot_number', 6)->firstOrFail();

        Livewire::test(FloorManager::class)
            ->call('toggleSlotType', $slot3->id)
            ->call('toggleSlotType', $slot6->id)
            ->call('confirmTypeChanges')
            ->assertSet('pendingTypeChanges', []);

        $this->assertSame([3, 6], $floor->slots()
            ->where('vehicle_type', 'two_wheeler')
            ->orderBy('slot_number')
            ->pluck('slot_number')
            ->all());
        $this->assertSame(4, $floor->slots()->where('vehicle_type', 'four_wheeler')->count());
    }

    public function test_floor_manager_second_tap_undoes_pending_change(): void
    {
        $floor = $this->makeFloor(0, 4);
        $slot = $floor->slots()->where('slot_number', 2)->firstOrFail();

        Livewire::test(FloorManager::class)
            ->call('toggleSlotType', $slot->id)
            ->call('toggleSlotType', $slot->id)
            ->assertSet('pendingTypeChanges', [])
            ->call('confirmTypeChanges');

        $this->assertDatabaseHas('parking_slots', [
            'id' => $slot->id,
            'vehicle_type' => 'four_wheeler',
        ]);
    }

    public function test_floor_manager_discard_drops_staged_changes(): void
    {
        $floor = $this->makeFloor(0, 4);
        $slot = $floor->slots()->where('slot_number', 1)->firstOrFail();

        Livewire::test(FloorManager::class)
            ->call('toggleSlotType', $slot->id)
            ->call('discardTypeChanges')
            ->assertSet('pendingTypeChanges', []);

        $this->assertDatabaseHas('parking_slots', [
            'id' => $slot->id,
            'vehicle_type' => 'four_wheeler',
        ]);
    }

    public function test_section_filter_scopes_floors_to_a_lot(): void
    {
        $this->makeFloor(0, 2); // Main Lot floor

        $otherLot = ParkingLot::create([
            'name' => 'Tower Lot',
            'lot_number' => 3610,
        ]);
        ParkingFloor::create([
            'name' => 'Tower Floor',
            'parking_lot_id' => $otherLot->id,
            'floor_number' => 0,
            'slot_count' => 3,
        ])->syncSlots();

        Livewire::test(FloorManager::class)
            ->set('filterLotId', $otherLot->id)
            ->assertSee('Tower Floor')
            ->assertDontSee('Floor 0')
            ->set('filterLotId', null)
            ->assertSee('Floor 0')
            ->assertSee('Tower Floor');
    }

    public function test_section_filter_and_add_form_lot_selection_are_independent(): void
    {
        $this->makeFloor(0, 2);

        $otherLot = ParkingLot::create([
            'name' => 'Tower Lot',
            'lot_number' => 3612,
        ]);
        ParkingFloor::create([
            'name' => 'Tower Floor',
            'parking_lot_id' => $otherLot->id,
            'floor_number' => 0,
            'slot_count' => 3,
        ])->syncSlots();

        // Filtering to one lot does not touch the add-floor form's selection.
        Livewire::test(FloorManager::class)
            ->set('filterLotId', $otherLot->id)
            ->assertSet('lotId', null)
            ->assertSee('Tower Floor')
            ->assertDontSee('Floor 0');

        // Picking a lot in the add form does not change the floors list filter.
        Livewire::test(FloorManager::class)
            ->set('lotId', $otherLot->id)
            ->assertSet('filterLotId', null)
            ->assertSee('Floor 0')
            ->assertSee('Tower Floor');
    }

    public function test_section_filter_select_uses_live_binding(): void
    {
        // Livewire 4: bare "wire:model" / ".change" only sync client state and the
        // URL for #[Url] properties without sending a network request. The filter
        // relies on ".live" to actually re-render the floors list.
        $this->get('/admin/floors')
            ->assertSee('wire:model.live.change="filterLotId"', false);
    }

    public function test_lot_report_counts_per_type_occupancy_from_slot_designation(): void
    {
        $floor = $this->makeFloor(0, 4);
        // Designate slot 2 (mid-row) as two-wheeler; occupy it and a four-wheeler.
        $floor->slots()->where('slot_number', 2)->update(['vehicle_type' => 'two_wheeler']);
        $floor->slots()->where('slot_number', 2)->update(['is_occupied' => true]);
        $floor->slots()->where('slot_number', 3)->update(['is_occupied' => true]);

        Livewire::test(LotOverview::class)
            ->assertViewHas('lots', function ($lots) {
                $this->assertSame(1, $lots->first()->two_wheeler_slots_count);
                $this->assertSame(1, $lots->first()->occupied_two_wheeler_slots_count);
                $this->assertSame(3, $lots->first()->four_wheeler_slots_count);
                $this->assertSame(1, $lots->first()->occupied_four_wheeler_slots_count);

                return true;
            })
            ->assertSee('2-Wheeler')
            ->assertSee('4-Wheeler');
    }

    public function test_lot_report_defaults_all_slots_to_four_wheeler_without_designation(): void
    {
        $this->makeFloor(0, 3);

        Livewire::test(LotOverview::class)
            ->assertViewHas('lots', function ($lots) {
                $this->assertSame(0, $lots->first()->two_wheeler_slots_count);
                $this->assertSame(3, $lots->first()->four_wheeler_slots_count);

                return true;
            });
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
            'name' => 'Other Lot',
            'lot_number' => 3606,
        ]);

        $otherFloor = ParkingFloor::create([
            'name' => 'Other Floor',
            'parking_lot_id' => $otherLot->id,
            'floor_number' => 0,
            'slot_count' => 3,
        ]);

        $otherFloor->syncSlots();

        // Both lots are selectable on the page.
        $this->get('/slots')
            ->assertOk()
            ->assertSee('Main Lot')
            ->assertSee('Other Lot')
            ->assertSee('#1 — Main Lot');

        // Selecting the other lot shows only its floors.
        Livewire::test(SlotDashboard::class)
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
            'name' => 'Downtown Lot',
            'lot_number' => 3607,
            'address' => '12 Market Street',
        ]);

        $otherFloor = ParkingFloor::create([
            'name' => 'Downtown Floor',
            'parking_lot_id' => $otherLot->id,
            'floor_number' => 0,
            'slot_count' => 6,
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
            'name' => 'Empty Lot',
            'lot_number' => 3609,
            'address' => '1 Nowhere Lane',
        ]);

        Livewire::test(LotOverview::class)
            ->assertOk()
            ->assertSee('Main Lot')
            ->assertDontSee('Empty Lot')
            ->assertViewHas('lots', fn ($lots) => $lots->every(fn ($lot) => $lot->total_slots > 0));
    }

    public function test_lot_report_searches_by_address(): void
    {
        $plazaLot = ParkingLot::create([
            'name' => 'Plaza Lot',
            'lot_number' => 3607,
            'address' => '99 Pine Avenue',
        ]);
        $plazaFloor = ParkingFloor::create([
            'name' => 'Plaza Floor',
            'parking_lot_id' => $plazaLot->id,
            'floor_number' => 0,
            'slot_count' => 2,
        ]);
        $plazaFloor->syncSlots();
        $this->makeFloor(0, 2); // Main Lot, no address

        Livewire::test(LotOverview::class)
            ->set('search', 'Pine Avenue')
            ->assertSee('Plaza Lot')
            ->assertViewHas('lots', fn ($lots) => $lots->count() === 1 && $lots->first()->name === 'Plaza Lot');

        Livewire::test(LotOverview::class)
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
            'name' => 'Busy Lot',
            'lot_number' => 3608,
        ]);

        $busyFloor = ParkingFloor::create([
            'name' => 'Busy Floor',
            'parking_lot_id' => $busyLot->id,
            'floor_number' => 0,
            'slot_count' => 4,
        ]);
        $busyFloor->syncSlots();
        $busyFloor->slots()->whereIn('slot_number', [1, 2, 3])->update(['is_occupied' => true]);

        Livewire::test(LotOverview::class)
            ->assertViewHas('lots', function ($lots) {
                return $lots->first()->free_slots === 2
                    && $lots->first()->name === 'Main Lot'
                    && $lots->last()->free_slots === 1
                    && $lots->last()->name === 'Busy Lot';
            });
    }
}
