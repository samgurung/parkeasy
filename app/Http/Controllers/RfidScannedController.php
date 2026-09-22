<?php

namespace App\Http\Controllers;

use App\Events\RfidScanned;
use App\Models\Entry;
use App\Models\Kiosk;
use App\Models\ParkingLot;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RfidScannedController extends Controller
{
    // Direction armed on the kiosk, used when the RFID reader posts a scan without a 'type'.
    protected const ARMED_MODE_CACHE_KEY = 'kiosk:armed_mode';

    public function rfidScanned(Request $request)
    {
        // validate input
        $data = $request->validate([
            'rfid_id' => 'required|string',
            'type' => 'sometimes|string|in:entry,exit',
            'lot' => 'required|integer|min:1',
            'kiosk' => 'sometimes|string',
        ]);

        $rfid = $data['rfid_id'];
        // The external reader scans without a type; use whichever direction is armed on the
        // kiosk. The mode stays armed for the whole shift and is scoped to the kiosk so one
        // kiosk arming ENTRY never changes how another kiosk scans.
        $type = $data['type'] ?? Cache::get($this->armedModeKey($data['kiosk'] ?? null));

        $lot = $this->lotFromNumber($data['lot']);
        if ($lot instanceof JsonResponse) {
            return $lot;
        }

        $notLinked = $this->rejectIfKioskNotLinked($rfid, $data, $lot);
        if ($notLinked) {
            return $notLinked;
        }

        // Without a direction we can't tell an entry from an exit, so refuse the scan instead of guessing.
        if (! $type) {
            broadcast(new RfidScanned($rfid, 'error', 'Select ENTRY or EXIT before scanning', null, null, null, null, $data['kiosk'] ?? null, $data['lot']));

            return response()->json([
                'success' => false,
                'error' => 'Select ENTRY or EXIT before scanning',
            ], 422);
        }

        $vehicle = Vehicle::where('rfid_id', $rfid)->first();

        if (! $vehicle) {
            // Exiting an unregistered card is always an error; only entry offers registration.
            if ($type === 'exit') {
                broadcast(new RfidScanned($rfid, 'error', 'Card not registered', null, null, null, null, $data['kiosk'] ?? null, $data['lot']));

                return response()->json([
                    'success' => false,
                    'error' => 'Card not registered',
                ], 404);
            }

            broadcast(new RfidScanned($rfid, 'unregistered', 'Card not registered', null, null, null, null, $data['kiosk'] ?? null, $data['lot']));

            return response()->json([
                'success' => false,
                'status' => 'unregistered',
                'rfid_id' => $rfid,
                'lot' => $lot->lot_number,
                'lot_name' => $lot->name,
                'error' => 'Card not registered',
            ], 404);
        }

        $activeEntry = $vehicle->entries()
            ->whereNull('exit_time')
            ->where('status', 'parked')
            ->latest()
            ->first();

        if ($type === 'exit') {
            if (! $activeEntry) {
                broadcast(new RfidScanned($rfid, 'error', 'No active entry for this card', null, null, null, null, $data['kiosk'] ?? null, $data['lot']));

                return response()->json([
                    'success' => false,
                    'error' => 'No active entry for this card',
                ], 422);
            }

            // A parked card is bound to the lot where it entered; it can only be
            // checked out from that lot's exit kiosk.
            if ($activeEntry->parking_lot_id && $activeEntry->parking_lot_id !== $lot->id) {
                broadcast(new RfidScanned($rfid, 'error', 'Vehicle is parked at a different lot', null, null, null, null, $data['kiosk'] ?? null, $data['lot']));

                return response()->json([
                    'success' => false,
                    'error' => 'Vehicle is parked at a different lot',
                ], 422);
            }

            return $this->closeEntry($vehicle, $activeEntry, $rfid, $lot, $data['kiosk'] ?? null);
        }

        // $type === 'entry', but the card is still inside
        if ($activeEntry) {
            broadcast(new RfidScanned($rfid, 'error', 'Card is already inside', null, null, null, null, $data['kiosk'] ?? null, $data['lot']));

            return response()->json([
                'success' => false,
                'error' => 'Card is already inside',
            ], 422);
        }

        // The entry is created only after the parking details are submitted.
        broadcast(new RfidScanned($rfid, 'details_required', null, null, null, null, null, $data['kiosk'] ?? null, $data['lot']));

        return response()->json([
            'success' => true,
            'status' => 'details_required',
            'rfid_id' => $rfid,
            'lot' => $lot->lot_number,
            'lot_name' => $lot->name,
            'vehicle_type' => $vehicle->vehicle_type,
            'time' => now()->toDateTimeString(),
        ]);
    }

    public function registerVehicle(Request $request)
    {
        $data = $request->validate([
            'rfid_id' => 'required|string|unique:vehicles,rfid_id',
            'name' => 'required|string|max:255',
            'phone' => 'required|digits:10',
            'vehicle_number' => 'required|string|max:255',
            'vehicle_type' => 'required|in:two_wheeler,four_wheeler',
            'lot' => 'required|integer|min:1',
            'kiosk' => 'sometimes|string',
        ]);

        $lot = $this->lotFromNumber($data['lot']);
        if ($lot instanceof JsonResponse) {
            return $lot;
        }

        $notLinked = $this->rejectIfKioskNotLinked($data['rfid_id'], $data, $lot);
        if ($notLinked) {
            return $notLinked;
        }

        $vehicle = Vehicle::create([
            'rfid_id' => $data['rfid_id'],
            'name' => $data['name'],
            'phone' => $data['phone'],
            'vehicle_type' => $data['vehicle_type'],
        ]);

        // Registration already captures driver, mobile and vehicle number, so park directly.
        $entry = $vehicle->entries()->create([
            'entry_time' => now(),
            'driver_name' => $data['name'],
            'mobile_number' => $data['phone'],
            'vehicle_number' => strtoupper($data['vehicle_number']),
            'vehicle_type' => $data['vehicle_type'],
            'status' => 'parked',
            'parking_lot_id' => $lot->id,
        ]);

        broadcast(new RfidScanned($vehicle->rfid_id, 'parked', null, $entry->id, null, $entry->vehicle_number, $entry->driver_name, $data['kiosk'] ?? null, $lot->lot_number, $entry->vehicle_type));

        return response()->json([
            'success' => true,
            'status' => 'parked',
            'entry_id' => $entry->id,
            'rfid_id' => $vehicle->rfid_id,
            'vehicle_number' => $entry->vehicle_number,
            'driver_name' => $entry->driver_name,
            'vehicle_type' => $entry->vehicle_type,
            'lot' => $lot->lot_number,
            'lot_name' => $lot->name,
            'time' => now()->toDateTimeString(),
        ]);
    }

    public function saveDetails(Request $request)
    {
        $data = $request->validate([
            'rfid_id' => 'required|string|exists:vehicles,rfid_id',
            'driver_name' => 'required|string|max:255',
            'vehicle_number' => 'required|string|max:255',
            'mobile_number' => 'required|string|max:10',
            'vehicle_type' => 'required|in:two_wheeler,four_wheeler',
            'lot' => 'required|integer|min:1',
            'kiosk' => 'sometimes|string',
        ]);

        $lot = $this->lotFromNumber($data['lot']);
        if ($lot instanceof JsonResponse) {
            return $lot;
        }

        $notLinked = $this->rejectIfKioskNotLinked($data['rfid_id'], $data, $lot);
        if ($notLinked) {
            return $notLinked;
        }

        $vehicle = Vehicle::where('rfid_id', $data['rfid_id'])->firstOrFail();
        $activeEntry = $vehicle->entries()
            ->whereNull('exit_time')
            ->where('status', 'parked')
            ->latest()
            ->first();

        if ($activeEntry) {
            return response()->json([
                'success' => false,
                'error' => 'Card is already inside',
            ], 422);
        }

        // Remember the vehicle type on the card so the next visit can skip asking.
        $vehicle->update(['vehicle_type' => $data['vehicle_type']]);

        $entry = $vehicle->entries()->create([
            'entry_time' => now(),
            'driver_name' => $data['driver_name'],
            'vehicle_number' => $data['vehicle_number'],
            'mobile_number' => $data['mobile_number'],
            'vehicle_type' => $data['vehicle_type'],
            'status' => 'parked',
            'parking_lot_id' => $lot->id,
        ]);

        $rfid = $vehicle->rfid_id;

        broadcast(new RfidScanned($rfid, 'parked', null, $entry->id, null, $entry->vehicle_number, $entry->driver_name, $data['kiosk'] ?? null, $lot->lot_number, $entry->vehicle_type));

        return response()->json([
            'success' => true,
            'status' => 'parked',
            'entry_id' => $entry->id,
            'rfid_id' => $rfid,
            'vehicle_number' => $entry->vehicle_number,
            'driver_name' => $entry->driver_name,
            'vehicle_type' => $entry->vehicle_type,
            'lot' => $lot->lot_number,
            'lot_name' => $lot->name,
            'time' => now()->toDateTimeString(),
        ]);
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

    protected function closeEntry(Vehicle $vehicle, Entry $activeEntry, string $rfid, ?ParkingLot $lot = null, ?string $kioskKey = null)
    {
        $exitTime = now();
        $amount = $this->calculateFee($activeEntry, $exitTime);

        $activeEntry->update([
            'status' => 'exited',
            'exit_time' => $exitTime,
            'amount' => $amount,
        ]);

        broadcast(new RfidScanned($rfid, 'exit', null, $activeEntry->id, $amount, $activeEntry->vehicle_number, $activeEntry->driver_name, $kioskKey, $lot?->lot_number ?? $activeEntry->parking_lot_id, $activeEntry->vehicle_type ?? $vehicle->vehicle_type));

        return response()->json([
            'success' => true,
            'status' => 'exit',
            'rfid_id' => $rfid,
            'amount' => $amount,
            'entry_id' => $activeEntry->id,
            'vehicle_number' => $activeEntry->vehicle_number,
            'driver_name' => $activeEntry->driver_name,
            'vehicle_type' => $activeEntry->vehicle_type ?? $vehicle->vehicle_type,
            'time' => $exitTime->toDateTimeString(),
        ]);
    }

    protected function calculateFee(Entry $entry, $exitTime): float
    {
        $hours = max(1, (int) ceil($entry->entry_time->diffInMinutes($exitTime) / 60));

        // Legacy entries may predate vehicle types; default to the four-wheeler rate.
        $vehicleType = $entry->vehicle_type ?? ParkingLot::VEHICLE_FOUR_WHEELER;

        $rate = $entry->parkingLot?->rateForVehicleType($vehicleType)
            ?? ($vehicleType === ParkingLot::VEHICLE_TWO_WHEELER ? 10.0 : 20.0);

        return $hours * $rate;
    }

    public function setMode(Request $request)
    {
        $data = $request->validate([
            'mode' => 'nullable|string|in:entry,exit',
            'kiosk' => 'sometimes|string',
        ]);

        $cacheKey = $this->armedModeKey($data['kiosk'] ?? null);

        if (empty($data['mode'])) {
            Cache::forget($cacheKey);
        } else {
            // Long TTL: the mode is meant to stay armed for an entire shift of scans, not a single one.
            Cache::put($cacheKey, $data['mode'], now()->addHours(12));
        }

        return response()->json(['success' => true]);
    }

    protected function armedModeKey(?string $kioskKey): string
    {
        return $kioskKey ? 'kiosk:'.$kioskKey.':armed_mode' : self::ARMED_MODE_CACHE_KEY;
    }
}
