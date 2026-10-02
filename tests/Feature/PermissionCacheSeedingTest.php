<?php

namespace Tests\Feature;

use App\Models\Access;
use App\Models\ParkingLot;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Spatie resolves permission names through a cache that is normally invalidated by model
 * events. `php artisan db:seed` unsets the model event dispatcher for the entire run, which
 * leaves that cache stale and made the seeder report permissions that were demonstrably in
 * the database. These tests reproduce the seeding environment exactly rather than calling
 * the seeder directly, because calling it directly hides the bug.
 */
class PermissionCacheSeedingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ParkingLot::query()->delete();
    }

    /**
     * Run the seeders the way `db:seed` does: the model event dispatcher removed for the
     * whole run, exactly as Illuminate\Database\Console\Seeds\SeedCommand does it.
     */
    private function seedLikeDbSeed(): void
    {
        $previous = Model::getEventDispatcher();

        Model::unsetEventDispatcher();

        try {
            (new DatabaseSeeder)->run();
        } finally {
            Model::setEventDispatcher($previous);
        }
    }

    public function test_it_seeds_with_model_events_suppressed_the_way_db_seed_does(): void
    {
        ParkingLot::create(['name' => 'Police Bazaar', 'lot_number' => 1, 'rate_two_wheeler' => 10, 'rate_four_wheeler' => 20]);

        $this->seedLikeDbSeed();

        $this->assertSame(ParkingLot::count(), User::role(User::ROLE_LOT_ADMIN)->count());
        $this->assertSame(9, Permission::count());
    }

    public function test_a_permission_written_during_seeding_is_immediately_resolvable_by_name(): void
    {
        // This is the assertion that used to throw. The row exists, so anything reporting
        // it as missing is reading a cache that was never refreshed.
        ParkingLot::create(['name' => 'Police Bazaar', 'lot_number' => 1, 'rate_two_wheeler' => 10, 'rate_four_wheeler' => 20]);

        $this->seedLikeDbSeed();

        $this->assertTrue(Permission::findByName(Access::MANAGE_FLOORS, 'web')->exists);
        $this->assertTrue(Permission::findByName(Access::MANAGE_LOTS, 'web')->exists);
    }

    public function test_it_works_against_a_database_that_already_has_permissions(): void
    {
        // The re-run case, which is what a developer hits day to day.
        ParkingLot::create(['name' => 'Police Bazaar', 'lot_number' => 1, 'rate_two_wheeler' => 10, 'rate_four_wheeler' => 20]);

        $this->seedLikeDbSeed();
        $this->seedLikeDbSeed();

        $this->assertSame(9, Permission::count(), 'Re-seeding must not duplicate permissions.');
        $this->assertSame(ParkingLot::count(), User::role(User::ROLE_LOT_ADMIN)->count());
    }

    public function test_it_works_when_the_permission_cache_was_warmed_before_the_permissions_existed(): void
    {
        // A cache warmed by an earlier boot, or copied from another environment. Without an
        // explicit reset this is what makes findOrCreate() trip over the unique index.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        ParkingLot::create(['name' => 'Police Bazaar', 'lot_number' => 1, 'rate_two_wheeler' => 10, 'rate_four_wheeler' => 20]);

        $this->seedLikeDbSeed();

        $this->assertSame(9, Permission::count());
    }

    public function test_the_role_permission_links_are_actually_written(): void
    {
        ParkingLot::create(['name' => 'Police Bazaar', 'lot_number' => 1, 'rate_two_wheeler' => 10, 'rate_four_wheeler' => 20]);

        $this->seedLikeDbSeed();

        $links = DB::table(config('permission.table_names.role_has_permissions'))->count();

        $this->assertGreaterThan(0, $links, 'Roles must end up with permissions attached.');

        // And the accounts must be able to act on them, which is the only thing that matters.
        $this->actingAs(User::role(User::ROLE_SUPER_ADMIN)->firstOrFail());
        $this->assertTrue(auth()->user()->can(Access::MANAGE_LOTS));
    }

    public function test_the_cache_agrees_with_the_database_after_a_sync(): void
    {
        Access::sync();

        // Whichever way the cache is left - empty, or warm from the lookups sync() performs -
        // it must not disagree with the table. A cache that claims fewer permissions than
        // exist is what makes an existing permission look missing.
        $cached = app(PermissionRegistrar::class)->getPermissions();

        $this->assertSame(
            Permission::count(),
            $cached->count(),
            'The cached permission set must match the database.'
        );

        foreach (Permission::pluck('name') as $name) {
            $this->assertContains($name, $cached->pluck('name')->all());
        }
    }

    public function test_a_second_sync_is_a_no_op(): void
    {
        Access::sync();
        Access::sync();

        $this->assertSame(9, Permission::count());
        $this->assertSame(2, Role::count());
    }
}
