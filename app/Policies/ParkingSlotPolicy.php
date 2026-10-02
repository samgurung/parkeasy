<?php

namespace App\Policies;

use App\Models\Access;
use App\Models\ParkingSlot;
use App\Models\User;

/**
 * A slot is reachable only through its floor's lot, so authorisation walks slot -> floor
 * -> lot. The relation is loaded here rather than by the caller so a policy check can
 * never be fooled by an id that has not been validated against its parent.
 */
class ParkingSlotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Access::MANAGE_SLOTS);
    }

    public function view(User $user, ParkingSlot $slot): bool
    {
        return $user->can(Access::MANAGE_SLOTS) && $user->administersLot($this->lotIdFor($slot));
    }

    public function update(User $user, ParkingSlot $slot): bool
    {
        return $this->view($user, $slot);
    }

    private function lotIdFor(ParkingSlot $slot): ?int
    {
        return $slot->floor?->parking_lot_id;
    }
}
