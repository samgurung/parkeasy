<?php

namespace Tests\Feature;

use App\Livewire\Admin\UserManager;
use App\Models\Access;
use App\Models\Kiosk;
use App\Models\ParkingLot;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithStaff;
use Tests\TestCase;

/**
 * The screen that hands out roles.
 *
 * Each test below is a way the naive version would have handed the wrong person the wrong
 * reach: an operator attached to a lot they do not work in, a lot admin quietly promoted, or
 * the only super admin removing themselves and locking everyone out of the panel.
 */
class UserManagerTest extends TestCase
{
    use InteractsWithStaff;

    private ParkingLot $lotA;

    private ParkingLot $lotB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpStaff();

        $this->lotA = $this->lot('Police Bazaar');
        $this->lotB = $this->lot('Tura Bus Stand');
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

    // ── Who may open the screen ────────────────────────────────────────────────

    public function test_a_super_admin_can_open_the_staff_screen(): void
    {
        $this->actingAsSuperAdmin();

        $this->get('/admin/users')->assertOk()->assertSee('Staff Accounts');
    }

    public function test_a_lot_admin_cannot_open_the_staff_screen(): void
    {
        // Managing accounts is the super admin's alone. A lot admin who could reach this screen
        // could make themselves one.
        $this->actingAsLotAdmin([$this->lotA]);

        $this->get('/admin/users')->assertForbidden();
    }

    public function test_an_operator_is_redirected_to_their_kiosk(): void
    {
        $this->actingAsOperator([$this->lotA]);

        $this->get('/admin/users')->assertRedirect(route('home'));
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get('/admin/users')->assertRedirect(route('login'));
    }

    public function test_the_staff_nav_link_is_shown_to_a_super_admin_only(): void
    {
        $this->actingAsSuperAdmin();
        $this->get('/admin/lots')->assertOk()->assertSee(route('admin.users'), escape: false);

        $this->actingAsLotAdmin([$this->lotA]);
        $this->get('/admin/lots')->assertOk()->assertDontSee(route('admin.users'), escape: false);
    }

    // ── Creating an operator ───────────────────────────────────────────────────

    public function test_it_creates_an_operator_scoped_to_the_selected_lot(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(UserManager::class)
            ->set('name', 'Bakiwe Marak')
            ->set('email', 'bakiwe@parkeasy.test')
            ->set('password', 'gate-password')
            ->set('role', User::ROLE_OPERATOR)
            ->set('lotIds', [$this->lotA->id])
            ->call('add')
            ->assertHasNoErrors();

        $operator = User::where('email', 'bakiwe@parkeasy.test')->firstOrFail();

        $this->assertTrue($operator->isOperator());
        $this->assertTrue($operator->administersLot($this->lotA));
        $this->assertFalse(
            $operator->administersLot($this->lotB),
            'An operator ticked for one lot must not quietly gain the other.'
        );
        $this->assertTrue(Hash::check('gate-password', $operator->password));
    }

    public function test_a_created_operator_reaches_only_its_own_lots_gates(): void
    {
        // The outcome the whole screen exists for: an operator can work one lot's gate and
        // cannot work another's, and cannot reach the admin panel at all.
        $ownKiosk = $this->kiosk($this->lotA, 'Gate A1', Kiosk::TYPE_ENTRY);
        $foreignKiosk = $this->kiosk($this->lotB, 'Gate B1', Kiosk::TYPE_ENTRY);

        $this->actingAsSuperAdmin();

        Livewire::test(UserManager::class)
            ->set('name', 'Bakiwe Marak')
            ->set('email', 'bakiwe@parkeasy.test')
            ->set('password', 'gate-password')
            ->set('role', User::ROLE_OPERATOR)
            ->set('lotIds', [$this->lotA->id])
            ->call('add')
            ->assertHasNoErrors();

        $operator = User::where('email', 'bakiwe@parkeasy.test')->firstOrFail();

        $this->assertTrue($this->canOperate($operator, $ownKiosk));
        $this->assertFalse($this->canOperate($operator, $foreignKiosk));
        $this->assertFalse($operator->canUseAdminPanel());
    }

    public function test_a_created_lot_admin_keeps_their_lot_admin_rights(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(UserManager::class)
            ->set('name', 'Rita Guria')
            ->set('email', 'rita@parkeasy.test')
            ->set('password', 'admin-password')
            ->set('role', User::ROLE_LOT_ADMIN)
            ->set('lotIds', [$this->lotA->id])
            ->call('add')
            ->assertHasNoErrors();

        $admin = User::where('email', 'rita@parkeasy.test')->firstOrFail();

        $this->assertTrue($admin->isLotAdmin());
        $this->assertFalse($admin->isOperator());
        $this->assertTrue($admin->canUseAdminPanel());
        $this->assertTrue($admin->can(Access::MANAGE_KIOSKS));
        $this->assertFalse($admin->can(Access::MANAGE_USERS));
        $this->assertTrue($admin->administersLot($this->lotA));
        $this->assertFalse($admin->administersLot($this->lotB));
    }

    public function test_it_refuses_to_attach_an_account_to_a_lot_the_caller_does_not_administer(): void
    {
        // Defence in depth. users.manage belongs to the super admin alone today, and a super
        // admin is never lot-scoped, so this branch is unreachable under the current
        // catalogue. It is here because the moment that permission is granted to a lesser role,
        // the same picker would otherwise hand a lot admin the power to attach an account to
        // another lot's gates.
        $this->actingAsLotAdmin([$this->lotA]);
        auth()->user()->givePermissionTo(Access::MANAGE_USERS);

        Livewire::test(UserManager::class)
            ->set('name', 'Bakiwe Marak')
            ->set('email', 'bakiwe@parkeasy.test')
            ->set('password', 'gate-password')
            ->set('role', User::ROLE_OPERATOR)
            ->set('lotIds', [$this->lotB->id])
            ->call('add')
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'bakiwe@parkeasy.test']);
    }

    public function test_the_lot_picker_only_offers_the_callers_own_lots(): void
    {
        // The picker itself, not just the save. Offering a foreign lot invites the click, and
        // leaves the refusal to be the only thing standing between a mistake and a breach.
        $this->actingAsLotAdmin([$this->lotA]);
        auth()->user()->givePermissionTo(Access::MANAGE_USERS);

        Livewire::test(UserManager::class)
            ->assertViewHas('lots', fn ($lots) => $lots->count() === 1 && $lots->first()->id === $this->lotA->id)
            ->assertSee('Police Bazaar')
            ->assertDontSee('Tura Bus Stand');
    }

    public function test_a_super_admin_account_is_never_attached_to_a_lot(): void
    {
        // A super admin's reach comes from the role, so a pivot row would be inert and would
        // make the navbar show a single lot for an account that administers all of them.
        $this->actingAsSuperAdmin();

        Livewire::test(UserManager::class)
            ->set('name', 'Break Glass')
            ->set('email', 'breakglass@parkeasy.test')
            ->set('password', 'break-glass-password')
            ->set('role', User::ROLE_SUPER_ADMIN)
            ->set('lotIds', [$this->lotA->id, $this->lotB->id])
            ->call('add')
            ->assertHasNoErrors();

        $account = User::where('email', 'breakglass@parkeasy.test')->firstOrFail();

        $this->assertCount(0, $account->lots);
        $this->assertTrue($account->administersLot($this->lotA));
        $this->assertTrue($account->administersLot($this->lotB));
    }

    public function test_it_rejects_a_duplicate_email(): void
    {
        $this->actingAsSuperAdmin();

        User::create(['name' => 'Existing', 'email' => 'taken@parkeasy.test', 'password' => 'password']);

        Livewire::test(UserManager::class)
            ->set('name', 'Impostor')
            ->set('email', 'taken@parkeasy.test')
            ->set('password', 'gate-password')
            ->set('role', User::ROLE_OPERATOR)
            ->set('lotIds', [$this->lotA->id])
            ->call('add')
            ->assertHasErrors(['email' => 'unique']);

        $this->assertSame(1, User::where('email', 'taken@parkeasy.test')->count());
    }

    public function test_it_rejects_a_short_password(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(UserManager::class)
            ->set('name', 'Bakiwe Marak')
            ->set('email', 'bakiwe@parkeasy.test')
            ->set('password', 'short')
            ->set('role', User::ROLE_OPERATOR)
            ->set('lotIds', [$this->lotA->id])
            ->call('add')
            ->assertHasErrors(['password' => 'min']);

        $this->assertDatabaseMissing('users', ['email' => 'bakiwe@parkeasy.test']);
    }

    // ── Editing ────────────────────────────────────────────────────────────────

    public function test_it_demotes_an_operator_to_a_lot_admin_and_rescopes_them(): void
    {
        $this->actingAsSuperAdmin();

        $operator = $this->operator([$this->lotA->id], ['email' => 'bakiwe@parkeasy.test']);

        Livewire::test(UserManager::class)
            ->call('edit', $operator->id)
            ->assertSet('editRole', User::ROLE_OPERATOR)
            ->set('editRole', User::ROLE_LOT_ADMIN)
            ->set('editLotIds', [$this->lotB->id])
            ->call('save')
            ->assertHasNoErrors();

        $operator = $operator->fresh();

        $this->assertTrue($operator->isLotAdmin());
        $this->assertFalse($operator->isOperator());
        $this->assertFalse($operator->administersLot($this->lotA));
        $this->assertTrue($operator->administersLot($this->lotB));
        $this->assertTrue($operator->canUseAdminPanel());
    }

    public function test_editing_the_role_or_lots_leaves_the_password_alone(): void
    {
        // The naive version either forces a password on every save, which resets the operator's
        // tablet mid-shift, or exposes the existing one in the form.
        $this->actingAsSuperAdmin();

        $operator = $this->operator([$this->lotA->id], [
            'email' => 'bakiwe@parkeasy.test',
            'password' => 'the-original-password',
        ]);

        Livewire::test(UserManager::class)
            ->call('edit', $operator->id)
            ->set('editName', 'Bakiwe Marak Jr')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(
            Hash::check('the-original-password', $operator->fresh()->password),
            'A rename must not rotate the password.'
        );
    }

    public function test_a_new_password_can_be_set_from_the_editor(): void
    {
        $this->actingAsSuperAdmin();

        $operator = $this->operator([$this->lotA->id], [
            'email' => 'bakiwe@parkeasy.test',
            'password' => 'the-original-password',
        ]);

        Livewire::test(UserManager::class)
            ->call('edit', $operator->id)
            ->set('editPassword', 'a-replacement-password')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('a-replacement-password', $operator->fresh()->password));
    }

    public function test_a_super_admin_cannot_demote_themselves(): void
    {
        // The one action that ends the installation: no super admin left to grant the role back.
        $super = $this->actingAsSuperAdmin();

        Livewire::test(UserManager::class)
            ->call('edit', $super->id)
            ->set('editRole', User::ROLE_OPERATOR)
            ->call('save')
            ->assertForbidden();

        $this->assertTrue($super->fresh()->isSuperAdmin());
    }

    public function test_a_super_admin_cannot_delete_themselves(): void
    {
        // The policy refuses this, so the confirmation step is already closed: there is no
        // state to reach from which the delete would go through.
        $super = $this->actingAsSuperAdmin();

        Livewire::test(UserManager::class)
            ->call('confirmDelete', $super->id)
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $super->id]);
    }

    public function test_a_mistyped_role_is_reported_as_a_typo_not_as_a_refusal(): void
    {
        // The self-demote check runs after validation, so a value that is not a role at all
        // has to fail as validation. Otherwise editing your own account with a typo looks like
        // a permissions problem, and the real fix is invisible.
        $super = $this->actingAsSuperAdmin();

        Livewire::test(UserManager::class)
            ->call('edit', $super->id)
            ->set('editRole', 'operatr')
            ->call('save')
            ->assertHasErrors(['editRole']);

        $this->assertTrue($super->fresh()->isSuperAdmin());
    }

    public function test_editing_an_account_into_a_foreign_lot_is_refused(): void
    {
        // Same reasoning as the create path: unreachable while users.manage is super-admin
        // only, and the reason it stays that way is that it refuses rather than saves.
        $this->actingAsLotAdmin([$this->lotA]);
        auth()->user()->givePermissionTo(Access::MANAGE_USERS);

        $operator = $this->operator([$this->lotA->id], ['email' => 'bakiwe@parkeasy.test']);

        Livewire::test(UserManager::class)
            ->call('edit', $operator->id)
            ->set('editLotIds', [$this->lotB->id])
            ->call('save')
            ->assertForbidden();

        $this->assertTrue($operator->fresh()->administersLot($this->lotA));
        $this->assertFalse($operator->fresh()->administersLot($this->lotB));
    }

    // ── Deleting ───────────────────────────────────────────────────────────────

    public function test_it_deletes_another_account(): void
    {
        $this->actingAsSuperAdmin();

        $operator = $this->operator([$this->lotA->id], ['email' => 'bakiwe@parkeasy.test']);

        Livewire::test(UserManager::class)
            ->call('confirmDelete', $operator->id)
            ->assertSet('deletingId', $operator->id)
            ->call('delete')
            ->assertSet('deletingId', null);

        $this->assertDatabaseMissing('users', ['id' => $operator->id]);
    }

    public function test_deleting_an_account_removes_its_lot_assignments(): void
    {
        $this->actingAsSuperAdmin();

        $operator = $this->operator([$this->lotA->id], ['email' => 'bakiwe@parkeasy.test']);

        Livewire::test(UserManager::class)->call('confirmDelete', $operator->id)->call('delete');

        $this->assertDatabaseMissing('user_parking_lot', ['user_id' => $operator->id]);
    }

    // ── The list ───────────────────────────────────────────────────────────────

    public function test_the_list_names_each_account_role_and_lot(): void
    {
        $this->actingAsSuperAdmin();

        $this->operator([$this->lotA->id], ['name' => 'Bakiwe Marak', 'email' => 'bakiwe@parkeasy.test']);

        $this->get('/admin/users')
            ->assertOk()
            ->assertSee('Bakiwe Marak')
            ->assertSee('bakiwe@parkeasy.test')
            ->assertSee('Operator')
            ->assertSee($this->lotA->name);
    }

    public function test_an_account_with_no_role_is_shown_as_reaching_nothing(): void
    {
        // Better to say so on the screen than to render an empty badge and leave the operator
        // wondering why the person can sign in and do nothing.
        $this->actingAsSuperAdmin();

        User::create(['name' => 'Rita Guria', 'email' => 'rita@parkeasy.test', 'password' => 'password']);

        $this->get('/admin/users')->assertOk()->assertSee('reaches nothing');
    }

    private function kiosk(ParkingLot $lot, string $name, string $type): Kiosk
    {
        return Kiosk::create([
            'name' => $name,
            'key' => Kiosk::makeKey($name),
            'type' => $type,
            'parking_lot_id' => $lot->id,
        ]);
    }

    private function canOperate(User $user, Kiosk $kiosk): bool
    {
        return Gate::forUser($user)->allows('operate', $kiosk);
    }
}
