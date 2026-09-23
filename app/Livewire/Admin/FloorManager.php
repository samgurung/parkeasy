<?php

namespace App\Livewire\Admin;

use App\Models\ParkingFloor;
use App\Models\ParkingLot;
use Illuminate\View\View;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

class FloorManager extends Component
{
    // ── Add-floor form ────────────────────────────────────────────────────────

    // Also scopes the page when visiting /admin/floors?lot=<id> (e.g. from the
    // "Floors & Slots" button on a lot), so the list stays focused on one lot.
    #[Rule('required|exists:parking_lots,id')]
    #[Url(as: 'lot')]
    public ?int $lotId = null;

    #[Rule('required|string|max:100')]
    public string $name = '';

    #[Rule('required|integer|min:1|max:500')]
    public string $slotCount = '';

    // ── Edit-floor form ───────────────────────────────────────────────────────

    public ?int $editingId = null;

    #[Rule('required|exists:parking_lots,id')]
    public ?int $editLotId = null;

    public string $editName = '';

    public string $editSlotCount = '';

    // ── Delete confirmation ───────────────────────────────────────────────────

    public ?int $confirmDeleteId = null;

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function render(): View
    {
        $lots = ParkingLot::orderBy('lot_number')->get();

        return view('livewire.admin.floor-manager', [
            'lots' => $lots,
            'selectedLot' => $lots->firstWhere('id', $this->lotId),
            'floors' => ParkingFloor::with('lot')->withCount([
                'slots',
                'slots as occupied_slots_count' => fn ($q) => $q->where('is_occupied', true),
            ])
                ->when($this->lotId, fn ($q) => $q->where('parking_lot_id', $this->lotId))
                ->orderBy('floor_number')
                ->get(),
        ])->layout('components.layouts.app', ['title' => 'Admin – Floors | ParkEasy']);
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
