<?php

namespace App\Models;

use App\Models\Entry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ParkingLot extends Model
{
    use HasFactory;

    public const VEHICLE_TWO_WHEELER = 'two_wheeler';

    public const VEHICLE_FOUR_WHEELER = 'four_wheeler';

    protected $fillable = [
        'name',
        'lot_number',
        'address',
        'rate_two_wheeler',
        'rate_four_wheeler',
    ];

    protected $casts = [
        'lot_number' => 'integer',
        'rate_two_wheeler' => 'float',
        'rate_four_wheeler' => 'float',
    ];

    /** @return HasMany<ParkingFloor> */
    public function floors(): HasMany
    {
        return $this->hasMany(ParkingFloor::class)->orderBy('floor_number');
    }

    /** @return HasManyThrough<ParkingSlot, ParkingFloor> */
    public function slots(): HasManyThrough
    {
        return $this->hasManyThrough(
            ParkingSlot::class,
            ParkingFloor::class,
            'parking_lot_id',   // FK on parking_floors
            'parking_floor_id', // FK on parking_slots
            'id',               // local key on parking_lots
            'id',               // local key on parking_floors
        );
    }

    /**
     * Entries currently parked at this lot (used for per-type occupancy telemetry).
     *
     * @return HasMany<Entry>
     */
    public function parkedEntries(): HasMany
    {
        return $this->hasMany(Entry::class)->where('status', 'parked');
    }

    /**
     * Auto-assign the next free lot number (1, 2, 3, …).
     */
    public static function nextLotNumber(): int
    {
        return (int) static::max('lot_number') + 1;
    }

    // ── Occupancy helpers ─────────────────────────────────────────────────────

    public function getTotalSlotsAttribute(): int
    {
        return (int) $this->floors()->sum('slot_count');
    }

    public function getOccupiedSlotsAttribute(): int
    {
        return (int) $this->slots()->where('is_occupied', true)->count();
    }

    public function getFreeSlotsAttribute(): int
    {
        return $this->total_slots - $this->occupied_slots;
    }

    public function getOccupancyPctAttribute(): int
    {
        return $this->total_slots > 0
            ? (int) round(($this->occupied_slots / $this->total_slots) * 100)
            : 0;
    }

    // ── Rates ─────────────────────────────────────────────────────────────────

    public function rateForVehicleType(?string $vehicleType): float
    {
        return $vehicleType === self::VEHICLE_TWO_WHEELER
            ? (float) $this->rate_two_wheeler
            : (float) $this->rate_four_wheeler;
    }
}
