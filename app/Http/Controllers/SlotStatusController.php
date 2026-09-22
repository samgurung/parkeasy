<?php

namespace App\Http\Controllers;

use App\Events\SlotStatusChanged;
use App\Models\ParkingFloor;
use App\Models\ParkingLot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlotStatusController extends Controller
{
    /**
     * Called by ESP32 IR sensor node to toggle slot occupancy.
     *
     * POST /api/slot-status
     * Body: { "lot": 1, "floor": 0, "slot": 3 }
     *
     * No status is transmitted; each hit toggles the slot:
     * free → occupied (vehicle entered), occupied → free (vehicle left).
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lot'   => 'required|integer|min:0',
            'floor' => 'required|integer|min:0',
            'slot'  => 'required|integer|min:1',
        ]);

        // Floors belong to a lot, and floor numbers restart per lot (0, 1, 2).
        // Look up the lot first (by its lot_number), then the floor within it.
        $lot = ParkingLot::where('lot_number', $data['lot'])->first();

        if (! $lot) {
            return response()->json([
                'success' => false,
                'error'   => 'Lot not found. Configure it in the admin panel first.',
            ], 404);
        }

        $floor = ParkingFloor::where('parking_lot_id', $lot->id)
            ->where('floor_number', $data['floor'])
            ->first();

        if (! $floor) {
            return response()->json([
                'success' => false,
                'error'   => 'Floor not found for this lot. Configure it in the admin panel first.',
            ], 404);
        }

        if ($data['slot'] > $floor->slot_count) {
            return response()->json([
                'success' => false,
                'error'   => "Slot {$data['slot']} exceeds configured slot count ({$floor->slot_count}) for this floor.",
            ], 404);
        }

        // Retrieve the slot row (should already exist via syncSlots).
        $slot = $floor->slots()->firstOrCreate(
            ['slot_number' => $data['slot']],
        );

        // Toggle: entry → occupied, exit → free. Consecutive hits alternate.
        $slot->update([
            'is_occupied'     => ! $slot->is_occupied,
            'last_updated_at' => now(),
        ]);

        // Re-load the relationship so the event gets fresh floor/lot data.
        $slot->load(['floor.lot']);

        broadcast(new SlotStatusChanged($slot));

        return response()->json([
            'success'      => true,
            'lot'          => $lot->lot_number,
            'lot_name'     => $lot->name,
            'floor'        => $floor->floor_number,
            'floor_name'   => $floor->name,
            'slot'         => $slot->slot_number,
            'status'       => $slot->is_occupied ? 'occupied' : 'free',
            'is_occupied'  => $slot->is_occupied,
            'updated_at'   => $slot->last_updated_at->toIso8601String(),
        ]);
    }
}
