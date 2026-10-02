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
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_LOT_ADMIN = 'lot_admin';

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
     * The lots this admin is responsible for. A super admin is attached to no lots and
     * instead sees everything; this relation is the authority for a lot admin's reach.
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

    /** Does this user administer the given lot? */
    public function administersLot(ParkingLot|int $lot): bool
    {
        $ids = $this->administeredLotIds();

        if ($ids === null) {
            return true;
        }

        return in_array($lot instanceof ParkingLot ? (int) $lot->id : (int) $lot, $ids, true);
    }
}
