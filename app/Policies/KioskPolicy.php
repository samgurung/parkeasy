<?php

namespace App\Policies;

use App\Models\Access;
use App\Models\Kiosk;
use App\Models\User;

/**
 * A kiosk is lot-scoped, with one deliberate exception: `parking_lot_id` is nullable, so a
 * kiosk that has not been attached to a lot yet has no owner. Only a super admin may
 * claim or configure an unattached kiosk - otherwise any lot admin could pull someone
 * else's unconfigured terminal into their own lot.
 */
class KioskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Access::MANAGE_KIOSKS);
    }

    public function view(User $user, Kiosk $kiosk): bool
    {
        if (! $user->can(Access::MANAGE_KIOSKS)) {
            return false;
        }

        return $kiosk->parking_lot_id === null
            ? $user->isSuperAdmin()
            : $user->administersLot($kiosk->parking_lot_id);
    }

    public function create(User $user): bool
    {
        return $user->can(Access::MANAGE_KIOSKS);
    }

    public function update(User $user, Kiosk $kiosk): bool
    {
        return $this->view($user, $kiosk);
    }

    public function delete(User $user, Kiosk $kiosk): bool
    {
        return $this->view($user, $kiosk);
    }
}
