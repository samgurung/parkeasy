<?php

namespace App\Livewire\Admin;

use App\Models\ParkingFloor;
use Illuminate\View\View;
use Livewire\Attributes\Rule;
use Livewire\Component;

class FloorManager extends Component
{
    // ── Add-floor form ────────────────────────────────────────────────────────

    #[Rule('required|string|max:100')]
    public string $name = '';

    #[Rule('required|integer|min:1|max:500')]
    public string $slotCount = '';

    // ── Edit-floor form ───────────────────────────────────────────────────────

    public ?int $editingId = null;

    public string $editName = '';

    public string $editSlotCount = '';

    // ── Delete confirmation ───────────────────────────────────────────────────

    public ?int $confirmDeleteId = null;

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.admin.floor-manager', [
            'floors' => ParkingFloor::withCount([
                'slots',
                'slots as occupied_slots_count' => fn ($q) => $q->where('is_occupied', true),
            ])->orderBy('floor_number')->get(),
        ])->layout('components.layouts.app', ['title' => 'Admin – Floors | ParkEasy']);
    }

    // ── Add floor ─────────────────────────────────────────────────────────────

    public function addFloor(): void
    {
        $this->validate([
            'name' => 'required|string|max:100',
            'slotCount' => 'required|integer|min:1|max:500',
        ]);

        // Auto-assign the next free floor number (1, 2, 3, ...).
        $floor = ParkingFloor::create([
            'name' => trim($this->name),
            'floor_number' => (int) ParkingFloor::max('floor_number') + 1,
            'slot_count' => (int) $this->slotCount,
        ]);

        $floor->syncSlots();

        $this->reset(['name', 'slotCount']);
        $this->dispatch('floor-saved');
    }

    // ── Edit floor ────────────────────────────────────────────────────────────

    public function startEdit(int $id): void
    {
        $floor = ParkingFloor::findOrFail($id);
        $this->editingId = $id;
        $this->editName = $floor->name;
        $this->editSlotCount = (string) $floor->slot_count;
    }

    public function saveEdit(): void
    {
        $this->validate([
            'editName' => 'required|string|max:100',
            'editSlotCount' => 'required|integer|min:1|max:500',
        ]);

        $floor = ParkingFloor::findOrFail($this->editingId);
        $floor->update([
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
