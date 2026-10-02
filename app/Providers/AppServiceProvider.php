<?php

namespace App\Providers;

use App\Models\Kiosk;
use App\Models\ParkingFloor;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\Vehicle;
use App\Policies\KioskPolicy;
use App\Policies\ParkingFloorPolicy;
use App\Policies\ParkingLotPolicy;
use App\Policies\ParkingSlotPolicy;
use App\Policies\UserPolicy;
use App\Policies\VehiclePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * Policies are mapped explicitly rather than relying on convention discovery, because
     * a missed mapping fails *open* for a lot admin: the model would fall back to
     * "allowed" instead of "denied". Being explicit makes the whole authorisation surface
     * reviewable in one place.
     */
    public function boot(): void
    {
        Gate::policy(ParkingLot::class, ParkingLotPolicy::class);
        Gate::policy(ParkingFloor::class, ParkingFloorPolicy::class);
        Gate::policy(ParkingSlot::class, ParkingSlotPolicy::class);
        Gate::policy(Kiosk::class, KioskPolicy::class);
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        // The super admin is the break-glass account, so it must not depend on the
        // permission cache being warm or on a role's permission set staying correct.
        // Returning null (rather than false) for everyone else keeps the normal policy
        // and permission checks in charge.
        Gate::before(fn (User $user): ?bool => $user->isSuperAdmin() ? true : null);
    }
}
