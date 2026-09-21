<?php

namespace App\Http\Controllers;

use App\Events\RfidScanned;
use App\Models\Entry;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RfidScannedController extends Controller
{
    // Flat hourly parking rate used to calculate the fee on exit.
    protected const RATE_PER_HOUR = 20;

    // Direction armed on the kiosk, used when the RFID reader posts a scan without a 'type'.
    protected const ARMED_MODE_CACHE_KEY = 'kiosk:armed_mode';

    public function rfidScanned(Request $request)
    {
        // validate input
        $data = $request->validate([
            'rfid_id' => 'required|string',
            'type' => 'sometimes|string|in:entry,exit',
        ]);

        $rfid = $data['rfid_id'];
        // The external reader scans without a type; use whichever direction is armed on the kiosk.
        // The mode stays armed across scans until the attendant switches or clears it.
        $type = $data['type'] ?? Cache::get(self::ARMED_MODE_CACHE_KEY);

        // Without a direction we can't tell an entry from an exit, so refuse the scan instead of guessing.
        if (! $type) {
            broadcast(new RfidScanned($rfid, 'error', 'Select ENTRY or EXIT before scanning'));

            return response()->json([
                'success' => false,
                'error' => 'Select ENTRY or EXIT before scanning',
            ], 422);
        }

        $vehicle = Vehicle::where('rfid_id', $rfid)->first();

        if (! $vehicle) {
            // Exiting an unregistered card is always an error; only entry offers registration.
            if ($type === 'exit') {
                broadcast(new RfidScanned($rfid, 'error', 'Card not registered'));

                return response()->json([
                    'success' => false,
                    'error' => 'Card not registered',
                ], 404);
            }

            broadcast(new RfidScanned($rfid, 'unregistered', 'Card not registered'));

            return response()->json([
                'success' => false,
                'status' => 'unregistered',
                'rfid_id' => $rfid,
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
                broadcast(new RfidScanned($rfid, 'error', 'No active entry for this card'));

                return response()->json([
                    'success' => false,
                    'error' => 'No active entry for this card',
                ], 422);
            }

            return $this->closeEntry($vehicle, $activeEntry, $rfid);
        }

        // $type === 'entry', but the card is still inside
        if ($activeEntry) {
            broadcast(new RfidScanned($rfid, 'error', 'Card is already inside'));

            return response()->json([
                'success' => false,
                'error' => 'Card is already inside',
            ], 422);
        }

        // The entry is created only after the parking details are submitted.
        broadcast(new RfidScanned($rfid, 'details_required'));

        return response()->json([
            'success' => true,
            'status' => 'details_required',
            'rfid_id' => $rfid,
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
        ]);

        $vehicle = Vehicle::create([
            'rfid_id' => $data['rfid_id'],
            'name' => $data['name'],
            'phone' => $data['phone'],
        ]);

        // Registration already captures driver, mobile and vehicle number, so park directly.
        $entry = $vehicle->entries()->create([
            'entry_time' => now(),
            'driver_name' => $data['name'],
            'mobile_number' => $data['phone'],
            'vehicle_number' => strtoupper($data['vehicle_number']),
            'status' => 'parked',
        ]);

        broadcast(new RfidScanned($vehicle->rfid_id, 'parked', null, $entry->id, null, $entry->vehicle_number, $entry->driver_name));

        return response()->json([
            'success' => true,
            'status' => 'parked',
            'entry_id' => $entry->id,
            'rfid_id' => $vehicle->rfid_id,
            'vehicle_number' => $entry->vehicle_number,
            'driver_name' => $entry->driver_name,
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
        ]);

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

        $entry = $vehicle->entries()->create([
            'entry_time' => now(),
            'driver_name' => $data['driver_name'],
            'vehicle_number' => $data['vehicle_number'],
            'mobile_number' => $data['mobile_number'],
            'status' => 'parked',
        ]);

        $rfid = $vehicle->rfid_id;

        broadcast(new RfidScanned($rfid, 'parked', null, $entry->id, null, $entry->vehicle_number, $entry->driver_name));

        return response()->json([
            'success' => true,
            'status' => 'parked',
            'entry_id' => $entry->id,
            'rfid_id' => $rfid,
            'vehicle_number' => $entry->vehicle_number,
            'driver_name' => $entry->driver_name,
            'time' => now()->toDateTimeString(),
        ]);
    }

    protected function closeEntry(Vehicle $vehicle, Entry $activeEntry, string $rfid)
    {
        $exitTime = now();
        $amount = $this->calculateFee($activeEntry->entry_time, $exitTime);

        $activeEntry->update([
            'status' => 'exited',
            'exit_time' => $exitTime,
            'amount' => $amount,
        ]);

        broadcast(new RfidScanned($rfid, 'exit', null, $activeEntry->id, $amount, $activeEntry->vehicle_number, $activeEntry->driver_name));

        return response()->json([
            'success' => true,
            'status' => 'exit',
            'rfid_id' => $rfid,
            'amount' => $amount,
            'entry_id' => $activeEntry->id,
            'vehicle_number' => $activeEntry->vehicle_number,
            'driver_name' => $activeEntry->driver_name,
            'time' => $exitTime->toDateTimeString(),
        ]);
    }

    protected function calculateFee($entryTime, $exitTime): float
    {
        $hours = max(1, (int) ceil($entryTime->diffInMinutes($exitTime) / 60));

        return $hours * self::RATE_PER_HOUR;
    }

    public function setMode(Request $request)
    {
        $data = $request->validate([
            'mode' => 'nullable|string|in:entry,exit',
        ]);

        if (empty($data['mode'])) {
            Cache::forget(self::ARMED_MODE_CACHE_KEY);
        } else {
            // Long TTL: the mode is meant to stay armed for an entire shift of scans, not a single one.
            Cache::put(self::ARMED_MODE_CACHE_KEY, $data['mode'], now()->addHours(12));
        }

        return response()->json(['success' => true]);
    }
}
