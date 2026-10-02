<?php

namespace Tests\Feature;

use App\Models\Access;
use App\Models\Kiosk;
use App\Models\ParkingLot;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The seeder provisions real credentials, so its edge cases are security behaviour rather
 * than cosmetic output. Each test below corresponds to a way the naive version handed the
 * wrong person the wrong lot.
 */
class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // An early migration leaves a "Main Lot" row behind, which would otherwise seed an
        // extra account and make every count in this file a guess. Start from a known state.
        ParkingLot::query()->delete();
    }

    private function lot(string $name): ParkingLot
    {
        return ParkingLot::create([
            'name' => $name,
            'lot_number' => ParkingLot::nextLotNumber(),
            'rate_two_wheeler' => 10,
            'rate_four_wheeler' => 20,
        ]);
    }

    /** The lot admin this seeder created for a given lot. */
    private function adminFor(ParkingLot $lot): User
    {
        return User::role(User::ROLE_LOT_ADMIN)
            ->whereHas('lots', fn ($q) => $q->where('parking_lots.id', $lot->id))
            ->firstOrFail();
    }

    /** The gate operator this seeder created for a given lot. */
    private function operatorFor(ParkingLot $lot): User
    {
        return User::role(User::ROLE_OPERATOR)
            ->whereHas('lots', fn ($q) => $q->where('parking_lots.id', $lot->id))
            ->firstOrFail();
    }

    private function seedAdmins(): void
    {
        $this->seed(AdminUserSeeder::class);
    }

    public function test_it_creates_a_super_admin_and_one_admin_and_operator_per_lot(): void
    {
        $this->lot('Police Bazaar');
        $this->lot('Tura Bus Stand');

        $this->seedAdmins();

        // Super admin, plus a lot admin and an operator for each of two lots.
        $this->assertSame(5, User::count());

        $super = User::where('email', 'superadmin@parkeasy.test')->firstOrFail();
        $this->assertTrue($super->isSuperAdmin());
        $this->assertFalse($super->isSuperAdmin() && $super->isLotAdmin());
        $this->assertCount(0, $super->lots);

        $this->assertSame(2, User::role(User::ROLE_LOT_ADMIN)->count());
        $this->assertSame(2, User::role(User::ROLE_OPERATOR)->count());
    }

    public function test_each_operator_is_attached_to_exactly_its_own_lot(): void
    {
        // The whole point of the role: an operator runs the gates of one lot, so seeding two
        // lots must not produce two accounts that can both work either gate.
        $a = $this->lot('Police Bazaar');
        $b = $this->lot('Tura Bus Stand');

        $this->seedAdmins();

        $operators = User::role(User::ROLE_OPERATOR)->get();

        $this->assertCount(2, $operators);

        foreach ($operators as $operator) {
            $this->assertCount(1, $operator->lots);
        }

        $this->assertNotSame(
            $this->operatorFor($a)->id,
            $this->operatorFor($b)->id,
            'Two lots must not share one gate operator.'
        );
        $this->assertFalse($this->operatorFor($a)->administersLot($b));
        $this->assertFalse($this->operatorFor($b)->administersLot($a));
    }

    public function test_a_seeded_operator_cannot_reach_the_admin_panel(): void
    {
        // The operator is seeded for convenience, so the seeder must not hand out a role that
        // can configure anything. This is the boundary the whole role exists to hold.
        $this->lot('Police Bazaar');

        $this->seedAdmins();

        $operator = $this->operatorFor(ParkingLot::firstOrFail());

        $this->assertTrue($operator->isOperator());
        $this->assertFalse($operator->canUseAdminPanel());
        $this->assertFalse($operator->can(Access::MANAGE_LOTS));
        $this->assertFalse($operator->can(Access::MANAGE_KIOSKS));
        $this->assertFalse($operator->can(Access::MANAGE_FLOORS));
        $this->assertFalse($operator->can(Access::MANAGE_USERS));
        $this->assertTrue($operator->can(Access::OPERATE_KIOSKS));
    }

    public function test_operator_addresses_survive_a_rename(): void
    {
        $lot = $this->lot('Tura Bus Stand');

        $this->seedAdmins();

        $before = $this->operatorFor($lot)->email;

        $lot->update(['name' => 'Renamed Depot']);

        $this->seedAdmins();

        $this->assertSame(3, User::count(), 'A rename must not spawn a second account per role.');
        $this->assertSame($before, $this->operatorFor($lot->fresh())->email);
        $this->assertTrue($this->operatorFor($lot->fresh())->administersLot($lot->fresh()));
    }

    public function test_a_seeded_operator_signs_in_with_their_own_address_when_none_is_configured(): void
    {
        config(['services.operator_password' => null]);

        $this->lot('Police Bazaar');

        $this->seedAdmins();

        $operator = $this->operatorFor(ParkingLot::firstOrFail());

        $this->assertTrue(
            Hash::check($operator->email, $operator->password),
            "An operator's default password is their own username, so seed credentials are readable from the address alone."
        );
    }

    public function test_a_configured_operator_password_is_honoured(): void
    {
        config(['services.operator_password' => 'a-deliberately-long-password']);

        $this->lot('Police Bazaar');

        $this->seedAdmins();

        $this->assertTrue(
            Hash::check('a-deliberately-long-password', User::role(User::ROLE_OPERATOR)->firstOrFail()->password)
        );
    }

    public function test_the_operator_password_is_independent_of_the_lot_admin_one(): void
    {
        // A shared operator password is a legitimate choice; letting it leak into the lot
        // admins would quietly widen who holds it.
        config([
            'services.operator_password' => 'operators-share-this',
            'services.lot_admin_password' => null,
        ]);

        $this->lot('Police Bazaar');

        $this->seedAdmins();

        $this->assertTrue(Hash::check('operators-share-this', $this->operatorFor(ParkingLot::firstOrFail())->password));
        $this->assertFalse(Hash::check('operators-share-this', $this->adminFor(ParkingLot::firstOrFail())->password));
    }

    public function test_lots_whose_names_reduce_to_the_same_slug_get_separate_accounts(): void
    {
        // "Tura Bus Stand" and "Tura-Bus-Stand" both reduce to tura.bus.stand. Deriving the
        // address from the name collapsed both lots onto one account, quietly giving one
        // person two lots and never creating the second admin at all.
        $a = $this->lot('Tura Bus Stand');
        $b = $this->lot('Tura-Bus-Stand');

        $this->seedAdmins();

        $admins = User::role(User::ROLE_LOT_ADMIN)->get();
        $this->assertCount(2, $admins, 'Two lots must not share one lot-admin account.');

        $forA = $admins->filter(fn ($u) => $u->administersLot($a));
        $forB = $admins->filter(fn ($u) => $u->administersLot($b));

        $this->assertCount(1, $forA);
        $this->assertCount(1, $forB);
        $this->assertNotSame(
            $forA->first()->id,
            $forB->first()->id,
            'Each of these lots needs a different human.'
        );
    }

    public function test_a_lot_named_after_the_super_admin_cannot_take_over_that_account(): void
    {
        // A lot called "Superadmin" once resolved to the super admin's own address, and the
        // lot loop then handed that account a second role and a lot to administer.
        $this->lot('Superadmin');

        $this->seedAdmins();

        $super = User::where('email', 'superadmin@parkeasy.test')->firstOrFail();

        $this->assertSame(
            [User::ROLE_SUPER_ADMIN],
            $super->getRoleNames()->all(),
            'The super admin must keep exactly one role.'
        );
        $this->assertCount(0, $super->lots, 'The super admin administers every lot, so holds none.');
    }

    public function test_lot_admin_addresses_survive_a_rename(): void
    {
        $lot = $this->lot('Tura Bus Stand');

        $this->seedAdmins();

        $before = $this->adminFor($lot)->email;

        $lot->update(['name' => 'Renamed Depot']);

        $this->seedAdmins();

        $this->assertSame(
            3, User::count(),
            'A rename must not spawn a second account: one super admin, one lot admin, one operator.'
        );
        // Still the same person, still administering the same lot.
        $this->assertSame($before, $this->adminFor($lot->fresh())->email);
        $this->assertTrue($this->adminFor($lot->fresh())->administersLot($lot->fresh()));
    }

    public function test_rerunning_does_not_reset_a_changed_password(): void
    {
        $lot = $this->lot('Police Bazaar');
        $this->seedAdmins();

        $admin = $this->adminFor($lot);
        $admin->update(['password' => Hash::make('a-password-chosen-by-a-human')]);

        $this->seedAdmins();

        $this->assertTrue(
            Hash::check('a-password-chosen-by-a-human', $admin->fresh()->password),
            'Re-seeding must not revert a password an operator has changed.'
        );
    }

    public function test_roles_are_authoritative_so_a_stale_role_is_corrected(): void
    {
        $this->lot('Police Bazaar');
        $this->seedAdmins();

        $admin = $this->adminFor(ParkingLot::firstOrFail());
        $admin->assignRole(User::ROLE_SUPER_ADMIN);

        $this->seedAdmins();

        $this->assertSame(
            [User::ROLE_LOT_ADMIN],
            $admin->fresh()->getRoleNames()->all(),
            'Re-seeding should correct a lot admin who was wrongly given another role.'
        );
    }

    public function test_a_lot_admin_signs_in_with_their_own_address_when_none_is_configured(): void
    {
        config(['services.lot_admin_password' => null]);

        $this->lot('Police Bazaar');
        $this->seedAdmins();

        $admin = $this->adminFor(ParkingLot::firstOrFail());

        $this->assertTrue(
            Hash::check($admin->email, $admin->password),
            "A lot admin's default password is their own username, so seed credentials are readable from the address alone."
        );
    }

    public function test_each_lot_admin_gets_a_different_default_password(): void
    {
        config(['services.lot_admin_password' => null]);

        $this->lot('Police Bazaar');
        $this->lot('Tura Bus Stand');
        $this->seedAdmins();

        $passwords = User::role(User::ROLE_LOT_ADMIN)->pluck('password');

        $this->assertCount(2, $passwords);
        $this->assertCount(2, $passwords->unique(), "One lot's leaked password must not open the next lot.");
    }

    public function test_the_super_admin_signs_in_with_the_published_default_when_none_is_configured(): void
    {
        config(['services.super_admin_password' => null]);

        $this->lot('Police Bazaar');
        $this->seedAdmins();

        $super = User::role(User::ROLE_SUPER_ADMIN)->firstOrFail();

        $this->assertTrue(Hash::check('superadmin123', $super->password));
    }

    public function test_a_configured_lot_admin_password_is_honoured(): void
    {
        config(['services.lot_admin_password' => 'a-deliberately-long-password']);

        $this->lot('Police Bazaar');
        $this->seedAdmins();

        $this->assertTrue(
            Hash::check('a-deliberately-long-password', User::role(User::ROLE_LOT_ADMIN)->firstOrFail()->password)
        );
    }

    public function test_lot_admins_are_reported_but_never_deleted_when_no_lot_matches(): void
    {
        $this->lot('Police Bazaar');
        $this->seedAdmins();

        $stale = $this->adminFor(ParkingLot::firstOrFail());
        $stale->update(['email' => 'retired.admin@parkeasy.test']);

        $this->seedAdmins();

        // Reported, not destroyed: the account may belong to a real person.
        $this->assertDatabaseHas('users', ['email' => 'retired.admin@parkeasy.test']);
    }

    public function test_operators_are_reported_but_never_deleted_when_no_lot_matches(): void
    {
        $this->lot('Police Bazaar');
        $this->seedAdmins();

        $stale = $this->operatorFor(ParkingLot::firstOrFail());
        $stale->update(['email' => 'retired.operator@parkeasy.test']);

        $this->seedAdmins();

        $this->assertDatabaseHas('users', ['email' => 'retired.operator@parkeasy.test']);
    }

    public function test_an_unassigned_lot_admin_administers_nothing(): void
    {
        // Failing closed is the whole point of a lot-scoped role.
        $lot = $this->lot('Police Bazaar');
        $this->seedAdmins();

        $admin = $this->adminFor($lot);
        $admin->lots()->sync([]);

        $this->assertFalse($admin->fresh()->administersLot($lot));
    }

    public function test_an_unassigned_operator_can_reach_no_gate(): void
    {
        // Same failure mode as the lot admin above, but the consequence is worse: an operator
        // with no lot must operate nothing rather than falling back to every lot.
        $lot = $this->lot('Police Bazaar');
        $kiosk = Kiosk::create([
            'name' => 'Main Gate A',
            'key' => Kiosk::makeKey('Main Gate A'),
            'type' => Kiosk::TYPE_ENTRY,
            'parking_lot_id' => $lot->id,
        ]);

        $this->seedAdmins();

        $operator = $this->operatorFor($lot);
        $operator->lots()->sync([]);

        $operator = $operator->fresh();

        $this->assertFalse($operator->administersLot($lot));
        $this->assertCount(0, $operator->operableKiosks());
        $this->assertFalse(Gate::forUser($operator)->allows('operate', $kiosk));
    }
}
