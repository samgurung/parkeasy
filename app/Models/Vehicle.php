<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $fillable = [
        'rfid_id',
        'vehicle_number',
        'vehicle_type',
        'driver_name',
        'mobile_number',
        'registered_by_user_id',
        'registered_at_lot_id',
    ];

    /** @return HasMany<Entry> */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /**
     * Who bound the card, for the audit trail. Nullable and never lot-scoped: a vehicle
     * registered at one lot is recognised at all of them.
     *
     * @return BelongsTo<User, $this>
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }

    /**
     * The lot whose gate this card was first bound at, when known. Records where the
     * registration happened; it deliberately does not scope the vehicle to that lot, which
     * is why the column is nullable and carries no uniqueness of its own.
     *
     * @return BelongsTo<ParkingLot, $this>
     */
    public function registeredAtLot(): BelongsTo
    {
        return $this->belongsTo(ParkingLot::class, 'registered_at_lot_id');
    }

    /**
     * The visit currently open for this vehicle, if any. A vehicle can only be
     * inside one lot at a time, so this is what an exit scan resolves against.
     */
    public function activeEntry(): ?Entry
    {
        return $this->entries()
            ->whereNull('exit_time')
            ->where('status', 'parked')
            ->latest('id')
            ->first();
    }
}
