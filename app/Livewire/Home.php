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
        // `/` is two pages behind one URL. A guest gets the front door: what the app is and a
        // way in, rather than a redirect that tells them nothing. Signing in replaces it with
        // the kiosk terminal.
        //
        // This is the login boundary, so it is worth being explicit about why it is here and
        // not in `->middleware('auth')` on the route. The terminal is the entry and exit
        // screen: from it a card is read, a vehicle is charged, a vehicle is released. An open
        // browser at a barrier would let whoever picked it up do all three. Two reasons to
        // branch instead of redirecting:
        //   - a first-time visitor gets an explanation instead of a login form.
        //   - the kiosk URL stays `/?kiosk=<key>` with no path prefix, which is what keeps
        //     every printed label and QR code already on a gate working.
        if (auth()->guest()) {
            return view('livewire.landing');
        }

        // Which kiosk this terminal is bound to is resolved by ResolveKioskBinding on
        // every web request, from the ?kiosk= URL on first visit and from the remembered
        // cookie or session after that, so a browser restart does not unbind the gate.
        // A kiosk is tied to a lot, so every scan from this page reports the right lot.
        $kiosk = request()->attributes->get('kiosk');

        return view('livewire.home', [
            'recentScans' => $this->recentScans($kiosk?->parking_lot_id),
            'kioskKey' => $kiosk?->key,
            'kioskName' => $kiosk?->name,
            // The registered gate type drives both the on-screen indicator and the direction
            // sent with a manual scan; the backend independently re-derives it from the kiosk,
            // so a tampered page cannot turn an entry gate into an exit gate.
            'kioskType' => $kiosk?->type,
            'kioskLotNumber' => $kiosk?->parkingLot?->lot_number,
            'kioskLotName' => $kiosk?->parkingLot?->name,
            'gateChoices' => $kiosk === null ? $this->gateChoices() : collect(),
        ]);
    }

    /**
     * The gates an operator may run, so the unbound page can offer them a choice.
     *
     * Empty for anyone else: staff reached this to preview one specific kiosk that has
     * already resolved, so a list would be noise. An operator with two or more gates is the
     * only real case - entry or exit is a genuine decision they have to make, and the answer
     * has to be recorded in the binding rather than guessed at.
     *
     * @return Collection<int, Kiosk>
     */
    protected function gateChoices(): Collection
    {
        $user = auth()->user();

        if (! $user?->operatesKiosks()) {
            return collect();
        }

        return $user->operableKiosks()->load('parkingLot');
    }

    /**
     * Rebuild the "recent scans" feed (newest first) from persisted entries, so the
     * list survives a page refresh instead of only living in the browser session.
     * Vehicle details are read through the entry's vehicle, since a card is
     * permanently bound to one vehicle.
     *
     * Scoped to the parking lot, not to a single kiosk. A lot has separate entry and exit
     * kiosks and the attendant needs sight of both: seeing that a card was admitted on the
     * entry side and released on the exit side is what tells them whether the vehicle is
     * still inside. Each row is tagged with the kiosk that handled it, so the list still
     * shows who did what.
     *
     * @return Collection<int, array{status: string, rfid_id: ?string, vehicle_number: ?string, driver_name: ?string, vehicle_type: ?string, amount: ?float, time: Carbon, entry_id: int, kiosk: ?string}>
     */
    protected function recentScans(?int $parkingLotId)
    {
        // An unlinked kiosk has no lot, so it has no scans to show.
        if ($parkingLotId === null) {
            return collect();
        }

        return Entry::with('vehicle')
            ->where('parking_lot_id', $parkingLotId)
            ->latest('updated_at')
            ->limit(20)
            ->get()
            ->flatMap(function (Entry $entry) {
                $events = collect();

                if ($entry->exit_time) {
                    $events->push([
                        'status' => 'exit',
                        'rfid_id' => $entry->rfid_id,
                        'vehicle_number' => $entry->vehicle?->vehicle_number,
                        'driver_name' => $entry->vehicle?->driver_name,
                        'vehicle_type' => $entry->vehicle?->vehicle_type,
                        'amount' => $entry->amount,
                        'time' => $entry->exit_time,
                        'entry_id' => $entry->id,
                        'kiosk' => $entry->exit_kiosk_key,
                    ]);
                }

                $events->push([
                    'status' => 'parked',
                    'rfid_id' => $entry->rfid_id,
                    'vehicle_number' => $entry->vehicle?->vehicle_number,
                    'driver_name' => $entry->vehicle?->driver_name,
                    'vehicle_type' => $entry->vehicle?->vehicle_type,
                    'amount' => null,
                    'time' => $entry->entry_time,
                    'entry_id' => $entry->id,
                    'kiosk' => $entry->entry_kiosk_key,
                ]);

                return $events;
            })
            ->sortByDesc('time')
            ->take(6)
            ->values();
    }
}
