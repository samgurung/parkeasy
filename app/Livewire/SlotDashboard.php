<?php

namespace App\Livewire;

use App\Models\ParkingFloor;
use Livewire\Component;

class SlotDashboard extends Component
{
    public function refresh(): void
    {
        // Triggers a re-render; called from the Refresh button.
    }

    public function render(): \Illuminate\View\View
    {
        $floors = ParkingFloor::with(['slots' => fn ($q) => $q->orderBy('slot_number')])
            ->orderBy('floor_number')
            ->get();

        $totalSlots    = $floors->sum('slot_count');
        $occupiedSlots = $floors->flatMap->slots->where('is_occupied', true)->count();
        $freeSlots     = $totalSlots - $occupiedSlots;
        $occupancyPct  = $totalSlots > 0 ? round(($occupiedSlots / $totalSlots) * 100) : 0;

        return view('livewire.slot-dashboard', compact(
            'floors',
            'totalSlots',
            'occupiedSlots',
            'freeSlots',
            'occupancyPct',
        ))->layout('components.layouts.app', ['title' => 'Slot Monitor | ParkEasy']);
    }
}
