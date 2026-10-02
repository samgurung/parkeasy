<?php

namespace App\Http\Controllers;

use App\Events\RfidScanned;
use App\Models\Entry;
use App\Models\Kiosk;
use App\Models\ParkingLot;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RfidScannedController extends Controller
{
    public function rfidScanned(Request $request)
    {
        // validate input
        $data = $request->validate([
            'rfid_id' => 'required|string',
            'lot' => 'required|integer|min:1',
            'kiosk' => 'required|string',
        ]);

        // The card code is the only thing tying a scan to a registered vehicle, so it is
        // normalised on every request. Readers differ on case (the ESP32 posts lowercase),
        // and an unmatched card would otherwise look unregistered.
        $rfid = $this->normaliseCard($data['rfid_id']);

        $lot = $this->lotFromNumber($data['lot']);
        if ($lot instanceof JsonResponse) {
            return $lot;
        }

        // The kiosk is the gate, so it decides the direction: an entry kiosk only admits
        // and an exit kiosk only releases. Nothing has to be selected before scanning.
        $notLinked = $this->rejectIfKioskNotLinked($rfid, $data, $lot);
        if ($notLinked) {
            return $notLinked;
        }

        $kiosk = Kiosk::where('key', (string) $data['kiosk'])->first();

        // A kiosk with no type can't gate traffic safely - admitting when it should release
        // would let a vehicle leave without being charged.
        if (! $kiosk->type) {
            return $this->kioskNotConfigured($rfid, $kiosk->key, $lot);
        }

        // The card is permanently bound to a single vehicle. A card that has never been seen
        // before is not an error - it is a first visit, so ask once and bind it for good.
        $vehicle = Vehicle::where('rfid_id', $rfid)->first();

        if ($kiosk->isExit()) {
            return $this->handleExit($rfid, $vehicle, $lot, $kiosk->key);
        }

        return $this->handleEntry($rfid, $vehicle, $lot, $kiosk->key);
    }

    /**
     * Refuse a scan from a kiosk that has not been told whether it admits or releases.
     */
    protected function kioskNotConfigured(string $rfid, string $kioskKey, ParkingLot $lot): JsonResponse
    {
        $message = 'This kiosk is not set to ENTRY or EXIT. Set it in the admin panel.';

        broadcast(new RfidScanned($rfid, 'error', $message, null, null, null, null, $kioskKey, $lot->lot_number));

        return response()->json([
            'success' => false,
            'error' => $message,
        ], 422);
    }

    protected function handleEntry(string $rfid, ?Vehicle $vehicle, ParkingLot $lot, ?string $kioskKey): JsonResponse
    {
        // A card we have never seen is a first visit, not a failure. We have no rate class
        // and no owner for it yet, so collect them once here and bind the card for good.
        if (! $vehicle) {
            broadcast(new RfidScanned($rfid, 'enrolment_required', null, null, null, null, null, $kioskKey, $lot->lot_number));

            return response()->json([
                'success' => true,
                'status' => 'enrolment_required',
                'rfid_id' => $rfid,
                'lot' => $lot->lot_number,
                'lot_name' => $lot->name,
                'time' => now()->toDateTimeString(),
            ]);
        }

        // Lock the card's row for the duration of the check-then-create. A double-tapped
        // kiosk would otherwise pass the "already parked" test twice and open two visits
        // for one vehicle, which would double-count it against occupancy.
        return DB::transaction(function () use ($rfid, $vehicle, $lot, $kioskKey) {
            Vehicle::whereKey($vehicle->id)->lockForUpdate()->first();

            $activeEntry = $vehicle->activeEntry();

            if ($activeEntry) {
                $message = 'Vehicle is already parked';

                broadcast(new RfidScanned($rfid, 'error', $message, null, null, $vehicle->vehicle_number, $vehicle->driver_name, $kioskKey, $lot->lot_number, $vehicle->vehicle_type));

                return response()->json([
                    'success' => false,
                    'error' => $message,
                ], 422);
            }

            $entry = Entry::create([
                'rfid_id' => $rfid,
                'vehicle_id' => $vehicle->id,
                'entry_time' => now(),
                'status' => 'parked',
                'parking_lot_id' => $lot->id,
                'entry_kiosk_key' => $kioskKey,
            ]);

            broadcast(new RfidScanned($rfid, 'parked', null, $entry->id, null, $vehicle->vehicle_number, $vehicle->driver_name, $kioskKey, $lot->lot_number, $vehicle->vehicle_type));

            return response()->json([
                'success' => true,
                'status' => 'parked',
                'entry_id' => $entry->id,
                'rfid_id' => $rfid,
                'vehicle_number' => $vehicle->vehicle_number,
                'driver_name' => $vehicle->driver_name,
                'vehicle_type' => $vehicle->vehicle_type,
                'lot' => $lot->lot_number,
                'lot_name' => $lot->name,
                'time' => now()->toDateTimeString(),
            ]);
        });
    }

    /**
     * Bind a card to its vehicle and park it in one step. This is the only time a driver
     * is asked anything: the details live on the vehicle, so every later visit is
     * resolved from the card alone.
     */
    public function enrol(Request $request)
    {
        $data = $request->validate([
            'rfid_id' => ['required', 'string', 'max:255'],
            'driver_name' => ['required', 'string', 'max:255'],
            'vehicle_number' => ['required', 'string', 'max:255'],
            'mobile_number' => ['required', 'string', 'size:10'],
            'vehicle_type' => ['required', Rule::in([ParkingLot::VEHICLE_TWO_WHEELER, ParkingLot::VEHICLE_FOUR_WHEELER])],
            'lot' => ['required', 'integer', 'min:1'],
            'kiosk' => ['required', 'string'],
        ]);

        $lot = $this->lotFromNumber($data['lot']);
        if ($lot instanceof JsonResponse) {
            return $lot;
        }

        $rfid = $this->normaliseCard($data['rfid_id']);

        $notLinked = $this->rejectIfKioskNotLinked($rfid, $data, $lot);
        if ($notLinked) {
            return $notLinked;
        }

        $kiosk = Kiosk::where('key', (string) $data['kiosk'])->first();

        if (! $kiosk->type) {
            return $this->kioskNotConfigured($rfid, $kiosk->key, $lot);
        }

        // Enrolment always parks the vehicle, so only an entry kiosk may do it. An exit
        // kiosk has no visit to attach the card to.
        if (! $kiosk->isEntry()) {
            $message = 'Cards can only be enrolled at an ENTRY kiosk';

            broadcast(new RfidScanned($rfid, 'error', $message, null, null, null, null, $kiosk->key, $lot->lot_number));

            return response()->json([
                'success' => false,
                'error' => $message,
            ], 422);
        }

        // The card may already have been enrolled at another kiosk between the scan that
        // opened this form and the submit, so fall through to the normal entry path.
        if ($vehicle = Vehicle::where('rfid_id', $rfid)->first()) {
            return $this->handleEntry($rfid, $vehicle, $lot, $kiosk->key);
        }

        return DB::transaction(function () use ($data, $rfid, $lot, $kiosk) {
            try {
                $vehicle = Vehicle::create([
                    'rfid_id' => $rfid,
                    'vehicle_number' => strtoupper(trim($data['vehicle_number'])),
                    'vehicle_type' => $data['vehicle_type'],
                    'driver_name' => trim($data['driver_name']),
                    'mobile_number' => $data['mobile_number'],
                    // This kiosk already knows which lot it serves, so the origin of the
                    // registration is free to record. It does not scope the vehicle: the
                    // card stays valid at every other lot.
                    'registered_at_lot_id' => $lot->id,
                ]);
            } catch (QueryException $e) {
                // Two kiosks enrolled the same card at once; the unique index caught it.
                $vehicle = Vehicle::where('rfid_id', $rfid)->first();

                if (! $vehicle) {
                    throw $e;
                }
            }

            $entry = Entry::create([
                'rfid_id' => $rfid,
                'vehicle_id' => $vehicle->id,
                'entry_time' => now(),
                'status' => 'parked',
                'parking_lot_id' => $lot->id,
                'entry_kiosk_key' => $kiosk->key,
            ]);

            broadcast(new RfidScanned($rfid, 'parked', null, $entry->id, null, $vehicle->vehicle_number, $vehicle->driver_name, $kiosk->key, $lot->lot_number, $vehicle->vehicle_type));

            return response()->json([
                'success' => true,
                'status' => 'parked',
                'entry_id' => $entry->id,
                'rfid_id' => $rfid,
                'enrolled' => true,
                'vehicle_number' => $vehicle->vehicle_number,
                'driver_name' => $vehicle->driver_name,
                'vehicle_type' => $vehicle->vehicle_type,
                'lot' => $lot->lot_number,
                'lot_name' => $lot->name,
                'time' => now()->toDateTimeString(),
            ]);
        });
    }

    protected function handleExit(string $rfid, ?Vehicle $vehicle, ParkingLot $lot, ?string $kioskKey): JsonResponse
    {
        $activeEntry = $vehicle?->activeEntry() ?? $this->activeEntry($rfid);

        if (! $activeEntry) {
            broadcast(new RfidScanned($rfid, 'error', 'No active entry for this card', null, null, null, null, $kioskKey, $lot->lot_number));

            return response()->json([
                'success' => false,
                'error' => 'No active entry for this card',
            ], 422);
        }

        // A parked vehicle is bound to the lot where it entered; it can only be
        // checked out from that lot's exit kiosk.
        if ($activeEntry->parking_lot_id && $activeEntry->parking_lot_id !== $lot->id) {
            broadcast(new RfidScanned($rfid, 'error', 'Vehicle is parked at a different lot', null, null, null, null, $kioskKey, $lot->lot_number));

            return response()->json([
                'success' => false,
                'error' => 'Vehicle is parked at a different lot',
            ], 422);
        }

        return $this->closeEntry($activeEntry, $rfid, $lot, $kioskKey);
    }

    /**
     * Card codes are matched case-insensitively everywhere they are stored, so a reader
     * that posts lowercase still finds the vehicle the card is bound to.
     */
    protected function normaliseCard(string $rfid): string
    {
        return strtoupper(trim($rfid));
    }

    protected function lotFromNumber(int $lotNumber): JsonResponse|ParkingLot
    {
        $lot = ParkingLot::where('lot_number', $lotNumber)->first();

        if (! $lot) {
            return response()->json([
                'success' => false,
                'error' => 'Lot not found. Configure it in the admin panel first.',
            ], 404);
        }

        return $lot;
    }

    /**
     * A scan may only be attributed to a kiosk that is currently linked to the
     * reported parking lot. A delinked kiosk is refused silently (so nothing
     * surfaces on its screen), while a mismatched key/lot pair broadcasts an
     * error to that kiosk so the operator sees why the scan failed.
     */
    protected function rejectIfKioskNotLinked(string $rfid, array $data, ParkingLot $lot): ?JsonResponse
    {
        if (empty($data['kiosk'])) {
            return null;
        }

        $kiosk = Kiosk::where('key', (string) $data['kiosk'])->first();

        if ($kiosk && ! $kiosk->parking_lot_id) {
            return response()->json([
                'success' => false,
                'error' => 'Kiosk is not linked to a parking lot',
            ], 422);
        }

        if (! $kiosk || $kiosk->parking_lot_id !== $lot->id) {
            broadcast(new RfidScanned($rfid, 'error', 'Kiosk is not linked to this parking lot', null, null, null, null, $data['kiosk'] ?? null, $data['lot']));

            return response()->json([
                'success' => false,
                'error' => 'Kiosk is not linked to this parking lot',
            ], 422);
        }

        return null;
    }

    protected function closeEntry(Entry $activeEntry, string $rfid, ?ParkingLot $lot = null, ?string $kioskKey = null)
    {
        $exitTime = now();
        $amount = $this->calculateFee($activeEntry, $exitTime);

        $activeEntry->update([
            'status' => 'exited',
            'exit_time' => $exitTime,
            'amount' => $amount,
            // The exit kiosk is recorded separately from the entry kiosk, so each kiosk
            // only lists the scans it actually handled.
            'exit_kiosk_key' => $kioskKey,
        ]);

        $vehicle = $activeEntry->vehicle;

        broadcast(new RfidScanned($rfid, 'exit', null, $activeEntry->id, $amount, $vehicle?->vehicle_number, $vehicle?->driver_name, $kioskKey, $lot?->lot_number ?? $activeEntry->parking_lot_id, $vehicle?->vehicle_type));

        return response()->json([
            'success' => true,
            'status' => 'exit',
            'rfid_id' => $rfid,
            'amount' => $amount,
            'entry_id' => $activeEntry->id,
            'vehicle_number' => $vehicle?->vehicle_number,
            'driver_name' => $vehicle?->driver_name,
            'vehicle_type' => $vehicle?->vehicle_type,
            'time' => $exitTime->toDateTimeString(),
        ]);
    }

    protected function calculateFee(Entry $entry, $exitTime): float
    {
        $hours = max(1, (int) ceil($entry->entry_time->diffInMinutes($exitTime) / 60));

        // Entries recorded before vehicle types existed fall back to the four-wheeler rate.
        $vehicleType = $entry->vehicle?->vehicle_type ?? ParkingLot::VEHICLE_FOUR_WHEELER;

        $rate = $entry->parkingLot?->rateForVehicleType($vehicleType)
            ?? ($vehicleType === ParkingLot::VEHICLE_TWO_WHEELER ? 10.0 : 20.0);

        return $hours * $rate;
    }

    /**
     * The visit currently open for a card, if any. Falls back to the raw card code so a
     * visit recorded against a since-deleted vehicle can still be checked out.
     */
    protected function activeEntry(string $rfid): ?Entry
    {
        return Entry::where('rfid_id', $rfid)
            ->whereNull('exit_time')
            ->where('status', 'parked')
            ->latest()
            ->first();
    }
}
