<?php

namespace App\Events;

use App\Models\ParkingSlot;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SlotStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** Sync broadcast – skip the queue so the ESP32 sees instant dashboard updates. */
    public string $broadcastQueue = 'null';

    public int $slotId;

    public int $floorNumber;

    public string $floorName;

    public int $slotNumber;

    public bool $isOccupied;

    public ?string $lastUpdatedAt;

    public function __construct(ParkingSlot $slot)
    {
        $floor = $slot->floor;

        $this->slotId        = $slot->id;
        $this->floorNumber   = $floor->floor_number;
        $this->floorName     = $floor->name;
        $this->slotNumber    = $slot->slot_number;
        $this->isOccupied    = $slot->is_occupied;
        $this->lastUpdatedAt = $slot->last_updated_at?->toIso8601String();
    }

    public function broadcastOn(): Channel
    {
        return new Channel('parking-slots');
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'slot_id'         => $this->slotId,
            'floor_number'    => $this->floorNumber,
            'floor_name'      => $this->floorName,
            'slot_number'     => $this->slotNumber,
            'is_occupied'     => $this->isOccupied,
            'last_updated_at' => $this->lastUpdatedAt,
        ];
    }
}
