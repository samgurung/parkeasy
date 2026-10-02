<?php

namespace App\Policies;

use App\Models\Access;
use App\Models\ParkingFloor;
use App\Models\User;

/**
 * Floors belong to a lot, so administration is a two-part test: the capability to manage
 * floors at all, and membership of the lot the floor is actually in. Both are required -
 * the capability alone would let a lot admin edit a competitor's layout.
 */
class ParkingFloorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Access::MANAGE_FLOORS);
    }

    public function view(User $user, ParkingFloor $floor): bool
    {
        return $user->can(Access::MANAGE_FLOORS) && $user->administersLot($floor->parking_lot_id);
    }

    public function create(User $user): bool
    {
        return $user->can(Access::MANAGE_FLOORS);
    }

    public function update(User $user, ParkingFloor $floor): bool
    {
        return $this->view($user, $floor);
    }

    public function delete(User $user, ParkingFloor $floor): bool
    {
        return $this->view($user, $floor);
    }
}
