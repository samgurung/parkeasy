<?php

namespace Tests\Concerns;

use App\Models\Access;
use App\Models\ParkingLot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Helpers for the staff-authorisation tests.
 *
 * Roles and permissions are seeded through the real catalogue (Access::sync) rather than
 * created ad hoc, so a test that passes here cannot pass against a permission set the app
 * would never actually grant.
 */
trait InteractsWithStaff
{
    use RefreshDatabase;

    protected function setUpStaff(): void
    {
        Access::sync();
    }

    /** A super admin, attached to no lots - their reach is every lot. */
    protected function superAdmin(array $attributes = []): User
    {
        $user = $this->makeUser($attributes, 'Super Admin');
        $user->assignRole(User::ROLE_SUPER_ADMIN);

        return $user->fresh();
    }

    /**
     * A lot admin attached to the given lots. With no lots passed, they administer nothing,
     * which is a state worth being able to test: an admin with no assignment must see an
     * empty list, not everyone's data.
     *
     * @param  array<int, int|ParkingLot>  $lots
     */
    protected function lotAdmin(array $lots = [], array $attributes = []): User
    {
        $user = $this->makeUser($attributes, 'Lot Admin');
        $user->assignRole(User::ROLE_LOT_ADMIN);
        $user->lots()->sync(
            collect($lots)->map(fn ($lot) => $lot instanceof ParkingLot ? $lot->id : $lot)->all()
        );

        return $user->fresh();
    }

    protected function actingAsSuperAdmin(): User
    {
        $user = $this->superAdmin();
        $this->actingAs($user);

        return $user;
    }

    /**
     * @param  array<int, int|ParkingLot>  $lots
     */
    protected function actingAsLotAdmin(array $lots = [], array $attributes = []): User
    {
        $user = $this->lotAdmin($lots, $attributes);
        $this->actingAs($user);

        return $user;
    }

    private function makeUser(array $attributes, string $name): User
    {
        static $sequence = 0;
        $sequence++;

        return User::create(array_merge([
            'name' => $name,
            'email' => "admin{$sequence}@parkeasy.test",
            'password' => 'password',
        ], $attributes));
    }
}
