<?php

namespace App\Policies;

use App\Models\Access;
use App\Models\User;

/**
 * Only a super admin manages accounts and lot assignments. A lot admin editing their own
 * role or attaching themselves to another lot would be a privilege escalation, so no
 * self-service path exists here at all.
 *
 * Note that the self-check in delete() never runs for a super admin: Gate::before answers true
 * for them before any policy method is reached. The one rule that actually protects the
 * break-glass account from deleting itself is asserted in the admin screen instead - see
 * UserManager::assertNotDeletingYourself().
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Access::MANAGE_USERS);
    }

    public function create(User $user): bool
    {
        return $user->can(Access::MANAGE_USERS);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(Access::MANAGE_USERS);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can(Access::MANAGE_USERS) && $user->id !== $model->id;
    }
}
