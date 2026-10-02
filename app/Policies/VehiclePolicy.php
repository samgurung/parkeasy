<?php

namespace App\Policies;

use App\Models\Access;
use App\Models\User;
use App\Models\Vehicle;

/**
 * The vehicle registry is site-wide, so this policy is the one place in the app that is
 * deliberately *not* lot-scoped.
 *
 * Reading is open to every admin because a card has to be looked up at whichever gate the
 * vehicle happens to turn up at. Writing is split: any admin may bind a card on arrival,
 * because a gate cannot refuse to admit a car just because the card is not yet known. But
 * editing and deleting are super-admin acts, since a correction or a deletion made in one
 * lot silently changes what every other gate believes about that vehicle.
 */
class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Access::VIEW_VEHICLES);
    }

    /**
     * The model argument is optional because a capability is also asked as a question
     * about the type - `can('update', Vehicle::class)` - when deciding which controls to
     * render before any particular row is in hand.
     */
    public function view(User $user, ?Vehicle $vehicle = null): bool
    {
        return $user->can(Access::VIEW_VEHICLES);
    }

    public function create(User $user): bool
    {
        return $user->can(Access::CREATE_VEHICLES);
    }

    public function update(User $user, ?Vehicle $vehicle = null): bool
    {
        return $user->can(Access::UPDATE_VEHICLES);
    }

    public function delete(User $user, ?Vehicle $vehicle = null): bool
    {
        return $user->can(Access::DELETE_VEHICLES);
    }
}
