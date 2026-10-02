<?php

namespace App\Livewire\Concerns;

use App\Models\ParkingLot;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Shared lot-scoping for the admin screens.
 *
 * Every admin component answers the same two questions: "may this person be here at all?"
 * and "which lots are they allowed to see?". Centralising that matters because a per-model
 * policy alone is not enough on a list screen - a policy governs one record, whereas a lot
 * admin's whole view of the world has to be narrowed in the query, or the records they
 * must not touch are still rendered in the table.
 */
trait ScopesToAdministeredLots
{
    /** Abort unless the signed-in user holds the given ability. */
    protected function authorizeAbility(string $ability): void
    {
        Gate::authorize($ability);
    }

    protected function user(): User
    {
        return auth()->user();
    }

    protected function isSuperAdmin(): bool
    {
        return $this->user()->isSuperAdmin();
    }

    /**
     * Every lot the signed-in user administers. For a super admin this is all of them;
     * for a lot admin it is exactly their assignment, which is also what the lot picker
     * should offer so the UI cannot be used to even *select* a foreign lot.
     *
     * @return Builder<ParkingLot>
     */
    protected function administeredLots(): Builder
    {
        return ParkingLot::query()
            ->administeredBy($this->user())
            ->orderBy('lot_number');
    }

    /**
     * A lot id the user may act on, or null when the id is missing or not theirs. Every
     * mutating action funnels its lot through here, so passing someone else's id is
     * treated exactly like passing an unknown one.
     */
    protected function authorizedLotId(mixed $lotId): ?int
    {
        $lotId = (int) $lotId;

        if ($lotId <= 0) {
            return null;
        }

        return $this->administeredLots()->whereKey($lotId)->exists() ? $lotId : null;
    }
}
