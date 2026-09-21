<?php

namespace App\Http\Controllers;

use App\Events\SlotStatusChanged;
use App\Models\ParkingFloor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlotStatusController extends Controller
{
    /**
     * Called by ESP32 IR sensor node to toggle slot occupancy.
     *
     * POST /api/slot-status
     * Body: { "floor": 0, "slot": 3 }
     *
     * No status is transmitted. Entering a slot breaks the IR beam once
     * (free → occupied); leaving breaks it again (occupied → free). Each
     * hit therefore toggles the slot's state.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'floor' => 'required|integer|min:0',
            'slot' => 'required|integer|min:1',
        ]);

        $floor = ParkingFloor::where('floor_number', $data['floor'])->first();

        if (! $floor) {
            return response()->json([
                'success' => false,
                'error' => 'Floor not found. Configure it in the admin panel first.',
            ], 404);
        }

        if ($data['slot'] > $floor->slot_count) {
            return response()->json([
                'success' => false,
                'error' => "Slot {$data['slot']} exceeds configured slot count ({$floor->slot_count}) for this floor.",
            ], 404);
        }

        // Retrieve the slot row (should already exist via syncSlots).
        // firstOrCreate guards against any timing edge-cases.
        $slot = $floor->slots()->firstOrCreate(
            ['slot_number' => $data['slot']],
        );

        // Toggle: entry → occupied, exit → free. Consecutive hits alternate.
        $slot->update([
            'is_occupied' => ! $slot->is_occupied,
            'last_updated_at' => now(),
        ]);

        // Re-load the relationship so the event gets fresh floor data.
        $slot->load('floor');

        broadcast(new SlotStatusChanged($slot));

        return response()->json([
            'success' => true,
            'floor' => $floor->floor_number,
            'floor_name' => $floor->name,
            'slot' => $slot->slot_number,
            'status' => $slot->is_occupied ? 'occupied' : 'free',
            'is_occupied' => $slot->is_occupied,
            'updated_at' => $slot->last_updated_at->toIso8601String(),
        ]);
    }
}
