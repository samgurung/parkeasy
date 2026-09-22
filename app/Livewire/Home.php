<?php

namespace App\Livewire;

use App\Models\Entry;
use App\Models\Kiosk;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

class Home extends Component
{
    public function render()
    {
        // The kiosk is tied to a parking lot via its admin-registered key (?kiosk=<key>),
        // so every scan made from this page reports the correct lot number. Persist the
        // key in the session so navigation back to / from other pages keeps the kiosk.
        $kioskKey = (string) request()->query('kiosk');

        if ($kioskKey !== '') {
            session(['kiosk_key' => $kioskKey]);
        } else {
            $kioskKey = (string) session('kiosk_key', '');
        }

        $kiosk = Kiosk::with('parkingLot')
            ->where('key', $kioskKey)
            ->first();

        return view('livewire.home', [
            'recentScans' => $this->recentScans(),
            'kioskKey' => $kiosk?->key,
            'kioskName' => $kiosk?->name,
            'kioskLotNumber' => $kiosk?->parkingLot?->lot_number,
            'kioskLotName' => $kiosk?->parkingLot?->name,
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
