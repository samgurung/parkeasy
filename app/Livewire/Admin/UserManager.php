<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ScopesToAdministeredLots;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Create staff accounts and say what each one may do.
 *
 * An account is a role plus a set of lots, and those two are not independent. A lot admin or an
 * operator reaches exactly the lots they are attached to, so the lot picker here is the thing
 * that decides what a person can actually touch - and it is scoped to the lots the person
 * running this screen administers, so the form cannot even *select* a foreign lot.
 *
 * One role per account, not a set. The role decides the account's whole reach: a super admin is
 * exempt from lot scoping by role, and an operator is refused the admin panel by role, so a
 * second role on the same account would be either inert or a privilege escalation waiting for
 * someone to notice the order it was applied in.
 */
class UserManager extends Component
{
    use ScopesToAdministeredLots;

    /**
     * The roles an account may be given, and what each one is for. Ordered by how much they
     * reach, because that same order decides which role wins on an account holding more than
     * one - see the navbar badge, which reads the first match.
     *
     * @var array<string, string>
     */
    public const ROLES = [
        User::ROLE_SUPER_ADMIN => 'Super admin — every lot, plus staff accounts',
        User::ROLE_LOT_ADMIN => 'Lot admin — their lots: floors, slots, kiosks, cards',
        User::ROLE_OPERATOR => 'Operator — their lot: run a gate terminal, nothing else',
    ];

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = User::ROLE_OPERATOR;

    /** @var array<int, int|string> */
    public array $lotIds = [];

    public ?int $editingId = null;

    public string $editName = '';

    public string $editEmail = '';

    public string $editPassword = '';

    public string $editRole = User::ROLE_OPERATOR;

    /** @var array<int, int|string> */
    public array $editLotIds = [];

    public ?int $deletingId = null;

    public function render(): View
    {
        return view('livewire.admin.user-manager', [
            // Roles and lots are eager loaded because every row renders both; without this the
            // table is two extra queries per account.
            'users' => User::with(['roles', 'lots'])
                ->orderBy('name')
                ->orderBy('email')
                ->get(),
            'lots' => $this->administeredLots()->get(),
        ])->layout('components.layouts.app', ['title' => 'Admin – Staff | ParkEasy']);
    }

    public function add(): void
    {
        Gate::authorize('create', User::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'role' => ['required', Rule::in(array_keys(self::ROLES))],
            'lotIds' => ['array'],
            'lotIds.*' => ['integer', 'exists:parking_lots,id'],
        ]);

        // Resolved before anything is written. An authorization failure has to leave no trace,
        // and an abort thrown after the insert would leave behind a real account with no role
        // and no lots - one that no screen can then show or anyone can clean up.
        $lotIds = $this->authorizedLotIds($validated['lotIds'] ?? [], $validated['role']);

        $user = User::create([
            'name' => trim($validated['name']),
            'email' => $validated['email'],
            // Hashed by the model's 'hashed' cast, so the plain value is what belongs here.
            'password' => $validated['password'],
        ]);

        $user->syncRoles($validated['role']);
        $user->lots()->sync($lotIds);

        $this->reset('name', 'email', 'password', 'lotIds');
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);
        Gate::authorize('update', $user);

        $this->editingId = $user->id;
        $this->editName = $user->name;
        $this->editEmail = $user->email;
        // Never round-tripped into the form: an editor that pre-filled it would put the
        // existing password into the DOM, and one that rejected a blank would force a reset on
        // every unrelated role change.
        $this->editPassword = '';
        $this->editRole = $this->primaryRole($user);
        $this->editLotIds = $user->lots->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function save(): void
    {
        $user = User::findOrFail($this->editingId);
        Gate::authorize('update', $user);

        $validated = $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editEmail' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'editPassword' => ['nullable', 'string', 'min:8', 'max:255'],
            'editRole' => ['required', Rule::in(array_keys(self::ROLES))],
            'editLotIds' => ['array'],
            'editLotIds.*' => ['integer', 'exists:parking_lots,id'],
        ]);

        // After validation, so a mistyped role is reported as the typo it is rather than as a
        // refusal to change your own role. Nothing has been written at this point, so the
        // order of the two checks costs nothing.
        //
        // A super admin who demotes themselves locks every account out of this screen, and the
        // only account that could undo it is the one just changed. Their own role is therefore
        // not theirs to edit, here or anywhere else.
        abort_if(
            $user->is($this->user()) && $validated['editRole'] !== $this->primaryRole($this->user()),
            403,
            'You cannot change your own role. Ask another super admin.'
        );

        // Before the write, for the same reason as the create path: a refused lot list must
        // not leave the name and email already changed.
        $lotIds = $this->authorizedLotIds($validated['editLotIds'] ?? [], $validated['editRole']);

        $user->name = trim($validated['editName']);
        $user->email = $validated['editEmail'];

        // Blank means leave it alone, so a role or lot change does not silently rotate the
        // password out from under the person using the tablet.
        if ($validated['editPassword']) {
            $user->password = $validated['editPassword'];
        }

        $user->save();

        $user->syncRoles($validated['editRole']);
        $user->lots()->sync($lotIds);

        $this->cancel();
    }

    public function cancel(): void
    {
        $this->editingId = null;
        $this->editName = '';
        $this->editEmail = '';
        $this->editPassword = '';
        $this->editLotIds = [];
    }

    public function confirmDelete(int $id): void
    {
        $user = User::findOrFail($id);
        Gate::authorize('delete', $user);

        $this->assertNotDeletingYourself($user);

        $this->deletingId = $user->id;
    }

    public function cancelDelete(): void
    {
        $this->deletingId = null;
    }

    public function delete(): void
    {
        $user = User::findOrFail($this->deletingId);

        Gate::authorize('delete', $user);
        $this->assertNotDeletingYourself($user);

        $user->delete();

        $this->deletingId = null;
    }

    /**
     * Nobody deletes their own account here.
     *
     * UserPolicy::delete says the same thing, but the clause is unreachable for the one role
     * that can open this screen: Gate::before answers true for a super admin before the policy
     * method is ever called, so the policy's own "not yourself" test never runs. It still earns
     * its place for any lesser role that is later granted users.manage, but the check that
     * actually protects the break-glass account has to be made here, where nothing can
     * short-circuit it - and it has to be made on both steps, since confirming and deleting are
     * separately callable.
     */
    protected function assertNotDeletingYourself(User $user): void
    {
        abort_if(
            $user->is($this->user()),
            403,
            'You cannot delete your own account.'
        );
    }

    /**
     * The lot ids an account may actually be attached to.
     *
     * Aborts on a lot the caller does not administer rather than quietly dropping it, so a
     * forged id is a loud 403 instead of a form that saved something other than what was asked.
     *
     * @param  array<int, mixed>  $ids
     * @return array<int, int>
     */
    protected function authorizedLotIds(array $ids, string $role): array
    {
        // A super admin reaches every lot by role, so an attachment would be inert. Keeping the
        // pivot empty is also what the seeder and every administeredLotIds() caller assume.
        if ($role === User::ROLE_SUPER_ADMIN) {
            return [];
        }

        $authorized = [];

        foreach ($ids as $id) {
            abort_unless(
                $lotId = $this->authorizedLotId($id),
                403,
                'You can only assign the lots you administer.'
            );

            $authorized[] = $lotId;
        }

        return array_values(array_unique($authorized));
    }

    /**
     * The role that decides what an account can reach.
     *
     * An account may hold several roles in the database, but everything that gates on a role -
     * this screen, the navbar badge, the admin route group - reads the first match in ROLES
     * order. The editor has to agree with them, or it would save a selection that does not
     * describe the account it is editing.
     */
    protected function primaryRole(User $user): string
    {
        foreach (array_keys(self::ROLES) as $role) {
            if ($user->hasRole($role)) {
                return $role;
            }
        }

        // No role at all: an account that can sign in and reach nothing. The operator is the
        // least of the three, so defaulting to it never hands out more than an empty account had.
        return User::ROLE_OPERATOR;
    }
}
