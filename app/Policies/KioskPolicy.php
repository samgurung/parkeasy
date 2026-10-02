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
 *
 * `operate` is a separate ability from the rest because it is granted to a different and
 * much smaller role. Configuring kiosks belongs to lot admins; running one belongs to
 * operators, who get nothing else on this model.
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

    /**
     * May this person run this terminal?
     *
     * Both halves matter. The permission keeps lot admins out - they configure kiosks
     * rather than stand at them - and the lot check keeps an operator at one gate out of
     * every other gate, which is the whole point of the role being per-lot. An unlinked
     * kiosk has no lot, so administersLot() fails on it and only the super admin's
     * Gate::before can wave it through; consistent with the policy's rule above.
     */
    public function operate(User $user, Kiosk $kiosk): bool
    {
        return $user->can(Access::OPERATE_KIOSKS)
            && $user->administersLot($kiosk->parking_lot_id);
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
