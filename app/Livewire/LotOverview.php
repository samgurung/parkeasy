<?php

namespace App\Livewire;

use App\Models\ParkingLot;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class LotOverview extends Component
{
    public string $search = '';

    public string $sortBy = 'free';

    public function refresh(): void
    {
        // Triggers a re-render; called from the Refresh button / poll.
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function render(): View
    {
        $lots = ParkingLot::query()
            ->withCount([
                'floors',
                'slots',
                'slots as occupied_slots_count' => fn ($q) => $q->where('is_occupied', true),
                'parkedEntries as parked_two_wheeler_count' => fn ($q) => $q->where('vehicle_type', 'two_wheeler'),
                'parkedEntries as parked_four_wheeler_count' => fn ($q) => $q->where('vehicle_type', 'four_wheeler'),
            ])
            // Only show lots that are actually configured with floors/slots.
            ->whereHas('slots')
            ->when(trim($this->search) !== '', function ($q) {
                $pattern = '%' . trim($this->search) . '%';

                $q->where(function ($q) use ($pattern) {
                    $q->where('name', 'like', $pattern)
                        ->orWhere('address', 'like', $pattern)
                        ->orWhere('lot_number', 'like', $pattern);
                });
            })
            ->get();

        $lots = $this->sort($lots);

        return view('livewire.lot-overview', [
            'lots' => $lots,
            'matchedAddress' => trim($this->search) !== '' ? trim($this->search) : null,
        ])->layout('components.layouts.app', ['title' => 'ParkEasy – Live Lot Report']);
    }

    // ── Sorting ───────────────────────────────────────────────────────────────

    private function sort(Collection $lots): Collection
    {
        return match ($this->sortBy) {
            'occupancy' => $lots->sortBy(fn ($lot) => $lot->occupancy_pct),
            'lot'       => $lots->sortBy(fn ($lot) => $lot->lot_number),
            default     => $lots->sortByDesc(fn ($lot) => $lot->free_slots),
        };
    }
}