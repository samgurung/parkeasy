<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_LOT_ADMIN = 'lot_admin';

    /** Gate staff: may run a kiosk terminal in their own lot, and nothing else. */
    public const ROLE_OPERATOR = 'operator';

    /**
     * Per-request cache for administeredLotIds(). Declared so the memo is a real property
     * rather than a dynamic one, which PHP would deprecate.
     *
     * @var array<int, int>|null
     */
    protected ?array $administered_lot_ids = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ── Lot assignment ────────────────────────────────────────────────────────

    /**
     * The lots this user acts within. A super admin is attached to no lots and instead sees
     * everything; this relation is the authority for everyone else's reach, including an
     * operator's, which is why it is named for the boundary rather than the role.
     *
     * @return BelongsToMany<ParkingLot>
     */
    public function lots(): BelongsToMany
    {
        // Spelled explicitly: Laravel would otherwise derive "parking_lot_user" by
        // alphabetising the model names, which is not the table that was created.
        return $this->belongsToMany(ParkingLot::class, 'user_parking_lot');
    }

    /** Super admins are not attached to lots; they administer every lot. */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    public function isLotAdmin(): bool
    {
        return $this->hasRole(self::ROLE_LOT_ADMIN);
    }

    public function isOperator(): bool
    {
        return $this->hasRole(self::ROLE_OPERATOR);
    }

    /**
     * May this account sign in and be sent straight to a gate?
     *
     * This is what distinguishes an account that belongs on the tablet from one that
     * merely opened a kiosk link to look at it. Answered from the role, never from
     * can(Access::OPERATE_KIOSKS): Gate::before waves the super admin through every check,
     * so a permission test here would hand the break-glass account the same treatment as
     * gate staff and quietly bind the super admin's own laptop to a gate.
     */
    public function operatesKiosks(): bool
    {
        return $this->isOperator();
    }

    /**
     * May this account use the admin panel at all?
     *
     * Stated once here because three places need the same answer - the lots policy, the
     * admin route group and the navigation - and an operator must be refused by all three.
     * Role-based rather than permission-based to keep the current staff set unchanged: the
     * panel has never been reachable on permissions alone, only on being staff.
     */
    public function canUseAdminPanel(): bool
    {
        return $this->isSuperAdmin() || $this->isLotAdmin();
    }

    /**
     * Ids of the lots this user administers, resolved once per request. A super admin
     * returns null to mean "no restriction", which is what lets scopeAdministeredLots()
     * skip the clause entirely.
     *
     * @return array<int, int>|null
     */
    public function administeredLotIds(): ?array
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        // Memoised on the instance: a list of floors would otherwise re-query per row.
        return $this->administered_lot_ids ??= $this->lots()
            ->pluck('parking_lots.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Does this user administer the given lot?
     *
     * A null lot - an unlinked kiosk, a slot whose lot has gone - is a refusal rather than an
     * error, and is answered after the super admin's exemption so that the break-glass account
     * keeps its reach. `KioskPolicy::operate()` depends on it: without it, an operator opening
     * a delinked gate's URL got a TypeError and a 500 instead of the 403 that was meant.
     */
    public function administersLot(ParkingLot|int|null $lot): bool
    {
        $ids = $this->administeredLotIds();

        if ($ids === null) {
            return true;
        }

        // Nobody administers a lot that does not exist, so nobody stands at its gate either.
        if ($lot === null) {
            return false;
        }

        return in_array($lot instanceof ParkingLot ? (int) $lot->id : (int) $lot, $ids, true);
    }

    // ── Where to send someone ─────────────────────────────────────────────────

    /**
     * Every kiosk this user may open a terminal for: the kiosks in the lots they
     * administer, and for a super admin every kiosk including the unclaimed ones.
     *
     * Deliberately wider than operableKiosks(). Configuring a kiosk is a lot admin's job
     * and standing at one is an operator's, but both have to be able to *reach* a gate
     * page: a lot admin covers the gate themselves when a lot has no dedicated operator,
     * and the terminal is how either of them checks a kiosk before handing it over. An
     * unclaimed kiosk is included for the super admin because the broken page it produces -
     * no lot, no gate type - is exactly what an admin needs to see while diagnosing it.
     *
     * @return Collection<int, Kiosk>
     */
    public function runnableKiosks(): Collection
    {
        return Kiosk::query()
            // A null lot list means the super admin, who is not restricted to a whereIn.
            ->when(
                ($lotIds = $this->administeredLotIds()) !== null,
                fn ($query) => $query->whereIn('parking_lot_id', $lotIds),
            )
            // Eager loaded because every caller renders the lot name beside the kiosk name.
            ->with('parkingLot')
            ->orderBy('parking_lot_id')
            ->orderBy('name')
            ->get();
    }

    /**
     * The kiosks this user may run as gate staff, in their lots. The gate terminal's picker
     * and the post-login redirect are both driven from this, so an operator sees the same
     * set of gates in both places.
     *
     * @return Collection<int, Kiosk>
     */
    public function operableKiosks(): Collection
    {
        if (! $this->operatesKiosks()) {
            return new Collection;
        }

        return $this->runnableKiosks();
    }

    /**
     * Where this user belongs after signing in.
     *
     * Staff go to the admin panel. An operator goes to their gate, and straight to it when
     * there is only one to choose from - a single-gate tablet should need no further taps
     * after the login it already performed. With two or more gates there is a genuine choice
     * to make (entry or exit), so they land on the unbound kiosk page and pick.
     */
    public function landingUrl(): string
    {
        if ($this->canUseAdminPanel()) {
            return route('admin.lots');
        }

        if (! $this->operatesKiosks()) {
            return route('login');
        }

        $kiosks = $this->operableKiosks();

        return $kiosks->count() === 1
            ? route('home', ['kiosk' => $kiosks->first()->key])
            : route('home');
    }
}
