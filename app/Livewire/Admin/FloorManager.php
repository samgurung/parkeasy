<?php

namespace App\Livewire\Admin;

use App\Models\ParkingFloor;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use Illuminate\View\View;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

class FloorManager extends Component
{
    // ── Add-floor form ────────────────────────────────────────────────────────

    #[Rule('required|exists:parking_lots,id')]
    public ?int $lotId = null;

    #[Rule('required|string|max:100')]
    public string $name = '';

    #[Rule('required|integer|min:1|max:500')]
    public string $slotCount = '';

    // ── Configured-floors filter ──────────────────────────────────────────────
    // Scopes the floors list to one lot. Driven by ?lot=<id> (e.g. from the
    // "Floors & Slots" button on a lot), independent of the add-floor form.

    #[Url(as: 'lot')]
    public ?int $filterLotId = null;

    // ── Edit-floor form ───────────────────────────────────────────────────────

    public ?int $editingId = null;

    #[Rule('required|exists:parking_lots,id')]
    public ?int $editLotId = null;

    public string $editName = '';

    public string $editSlotCount = '';

    // ── Delete confirmation ───────────────────────────────────────────────────

    public ?int $confirmDeleteId = null;

    // ── Pending slot type changes (staged, applied via confirm bar) ───────────
    // Keyed by slot id holding the type the slot will become once applied.

    public array $pendingTypeChanges = [];

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function render(): View
    {
        $lots = ParkingLot::withCount('floors')->orderBy('lot_number')->get();
        $selectedLot = $lots->firstWhere('id', $this->filterLotId);

        $floors = ParkingFloor::with(['lot'])->with(['slots' => fn ($q) => $q->select([
            'id', 'parking_floor_id', 'slot_number', 'vehicle_type', 'is_occupied',
        ])])->withCount([
            'slots',
            'slots as occupied_slots_count' => fn ($q) => $q->where('is_occupied', true),
            'slots as two_wheeler_slots_count' => fn ($q) => $q->where('vehicle_type', ParkingLot::VEHICLE_TWO_WHEELER),
        ])
            ->when($this->filterLotId, fn ($q) => $q->where('parking_lot_id', $this->filterLotId))
            ->orderBy('floor_number')
            ->get();

        // When scoped to a lot we show one group; otherwise group by lot so the
        // list stays navigable even with many lots and floors.
        $floorGroups = $this->filterLotId
            ? collect([['lot' => $selectedLot, 'floors' => $floors]])
            : $floors->groupBy('parking_lot_id')
                ->map(fn ($group, $lotId) => [
                    'lot' => $lots->firstWhere('id', (int) $lotId),
                    'floors' => $group,
                ])
                ->values();

        return view('livewire.admin.floor-manager', [
            'lots' => $lots,
            'selectedLot' => $selectedLot,
            'floorGroups' => $floorGroups,
        ])->layout('components.layouts.app', ['title' => 'Admin – Floors | ParkEasy']);
    }

    // ── Slot type designation ─────────────────────────────────────────────────

    /** Stage a slot type flip; nothing is persisted until confirmTypeChanges(). */
    public function toggleSlotType(int $slotId): void
    {
        // Tapping a pending chip again reverts the staged change.
        if (array_key_exists($slotId, $this->pendingTypeChanges)) {
            unset($this->pendingTypeChanges[$slotId]);

            return;
        }

        $slot = ParkingSlot::findOrFail($slotId);

        $this->pendingTypeChanges[$slotId] = $slot->vehicle_type === ParkingLot::VEHICLE_TWO_WHEELER
            ? ParkingLot::VEHICLE_FOUR_WHEELER
            : ParkingLot::VEHICLE_TWO_WHEELER;
    }

    /** Persist all staged slot type changes. */
    public function confirmTypeChanges(): void
    {
        if ($this->pendingTypeChanges === []) {
            return;
        }

        foreach ($this->pendingTypeChanges as $slotId => $type) {
            ParkingSlot::where('id', $slotId)->update(['vehicle_type' => $type]);
        }

        $this->pendingTypeChanges = [];
        $this->dispatch('floor-saved');
    }

    public function discardTypeChanges(): void
    {
        $this->pendingTypeChanges = [];
    }

    // ── Add floor ─────────────────────────────────────────────────────────────

    public function addFloor(): void
    {
        $this->validate([
            'lotId' => 'required|exists:parking_lots,id',
            'name' => 'required|string|max:100',
            'slotCount' => 'required|integer|min:1|max:500',
        ]);

        // Auto-assign the next free floor number within the chosen lot.
        $nextFloor = ParkingFloor::where('parking_lot_id', $this->lotId)
            ->max('floor_number') + 1;

        $floor = ParkingFloor::create([
            'parking_lot_id' => $this->lotId,
            'name' => trim($this->name),
            'floor_number' => (int) $nextFloor,
            'slot_count' => (int) $this->slotCount,
        ]);

        $floor->syncSlots();

        // Keep the chosen lot selected so the attendant can keep adding floors
        // to the same lot (and the ?lot= scope stays intact).
        $this->reset(['name', 'slotCount']);
        $this->dispatch('floor-saved');
    }

    // ── Edit floor ────────────────────────────────────────────────────────────

    public function startEdit(int $id): void
    {
        $floor = ParkingFloor::findOrFail($id);
        $this->editingId = $id;
        $this->editLotId = $floor->parking_lot_id;
        $this->editName = $floor->name;
        $this->editSlotCount = (string) $floor->slot_count;
    }

    public function saveEdit(): void
    {
        $this->validate([
            'editLotId' => 'required|exists:parking_lots,id',
            'editName' => 'required|string|max:100',
            'editSlotCount' => 'required|integer|min:1|max:500',
        ]);

        $floor = ParkingFloor::findOrFail($this->editingId);

        $floor->update([
            'parking_lot_id' => $this->editLotId,
            'name' => trim($this->editName),
            'slot_count' => (int) $this->editSlotCount,
        ]);

        $floor->syncSlots();

        $this->cancelEdit();
        $this->dispatch('floor-saved');
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editName = '';
        $this->editSlotCount = '';
    }

    // ── Delete floor ──────────────────────────────────────────────────────────

    public function confirmDelete(int $id): void
    {
        $this->confirmDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteId = null;
    }

    public function deleteFloor(): void
    {
        ParkingFloor::findOrFail($this->confirmDeleteId)->delete();
        $this->confirmDeleteId = null;
    }
}
