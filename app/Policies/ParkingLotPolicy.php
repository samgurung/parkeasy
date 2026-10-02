<?php

namespace App\Policies;

use App\Models\Access;
use App\Models\ParkingLot;
use App\Models\User;

/**
 * Lots are site-wide configuration, so creating or reshaping one is a super-admin act.
 * A lot admin may read their own lots - the nav and pickers need that - but may not
 * invent new ones or re-point one at a different site.
 */
class ParkingLotPolicy
{
    public function viewAny(User $user): bool
    {
        // Null means "may the panel be used at all", asked before any lot is chosen. Role
        // based rather than permission based, because the panel has only ever been
        // reachable by staff - an operator is refused here as well as by the route group.
        return $user->canUseAdminPanel();
    }

    public function view(User $user, ?ParkingLot $lot = null): bool
    {
        // Null means "any lot" - the capability question asked before a lot is chosen.
        return $lot === null || $user->administersLot($lot);
    }

    public function create(User $user): bool
    {
        return $user->can(Access::MANAGE_LOTS);
    }

    public function update(User $user, ?ParkingLot $lot = null): bool
    {
        return $user->can(Access::MANAGE_LOTS);
    }

    public function delete(User $user, ?ParkingLot $lot = null): bool
    {
        return $user->can(Access::MANAGE_LOTS);
    }
}
