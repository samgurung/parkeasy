<?php

namespace App\Livewire;

use App\Models\ParkingFloor;
use App\Models\ParkingLot;
use Livewire\Component;

class SlotDashboard extends Component
{
    public ?int $lotId = null;

    public function mount(): void
    {
        $this->lotId = ParkingLot::orderBy('lot_number')->value('id');
    }

    public function refresh(): void
    {
        // Triggers a re-render; called from the Refresh button.
    }

    public function render(): \Illuminate\View\View
    {
        $lots = ParkingLot::orderBy('lot_number')->get();

        $floors = ParkingFloor::query()
            ->with(['lot', 'slots' => fn ($q) => $q->orderBy('slot_number')])
            ->when($this->lotId, fn ($q) => $q->where('parking_lot_id', $this->lotId))
            ->orderBy('floor_number')
            ->get();

        $selectedLot = $lots->firstWhere('id', $this->lotId);

        $totalSlots    = $floors->sum('slot_count');
        $occupiedSlots = $floors->flatMap->slots->where('is_occupied', true)->count();
        $freeSlots     = $totalSlots - $occupiedSlots;
        $occupancyPct  = $totalSlots > 0 ? round(($occupiedSlots / $totalSlots) * 100) : 0;

        return view('livewire.slot-dashboard', compact(
            'lots',
            'floors',
            'selectedLot',
            'totalSlots',
            'occupiedSlots',
            'freeSlots',
            'occupancyPct',
        ))->layout('components.layouts.app', ['title' => 'Slot Monitor | ParkEasy']);
    }
}