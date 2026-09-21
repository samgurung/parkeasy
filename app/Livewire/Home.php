<?php

namespace App\Livewire;

use App\Models\Entry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

class Home extends Component
{
    public function render()
    {
        return view('livewire.home', [
            'recentScans' => $this->recentScans(),
        ]);
    }

    /**
     * Rebuild the "recent scans" feed (newest first) from persisted entries, so the
     * list survives a page refresh instead of only living in the browser session.
     *
     * @return Collection<int, array{status: string, rfid_id: ?string, vehicle_number: ?string, driver_name: ?string, amount: ?float, time: Carbon, entry_id: int}>
     */
    protected function recentScans()
    {
        return Entry::with('vehicle')
            ->latest('updated_at')
            ->limit(20)
            ->get()
            ->flatMap(function (Entry $entry) {
                $events = collect();

                if ($entry->exit_time) {
                    $events->push([
                        'status' => 'exit',
                        'rfid_id' => $entry->vehicle?->rfid_id,
                        'vehicle_number' => $entry->vehicle_number,
                        'driver_name' => $entry->driver_name,
                        'amount' => $entry->amount,
                        'time' => $entry->exit_time,
                        'entry_id' => $entry->id,
                    ]);
                }

                $events->push([
                    'status' => 'parked',
                    'rfid_id' => $entry->vehicle?->rfid_id,
                    'vehicle_number' => $entry->vehicle_number,
                    'driver_name' => $entry->driver_name,
                    'amount' => null,
                    'time' => $entry->entry_time,
                    'entry_id' => $entry->id,
                ]);

                return $events;
            })
            ->sortByDesc('time')
            ->take(6)
            ->values();
    }
}
