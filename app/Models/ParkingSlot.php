<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParkingSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'parking_floor_id',
        'slot_number',
        'label',
        'is_occupied',
        'last_updated_at',
    ];

    protected $casts = [
        'slot_number'     => 'integer',
        'is_occupied'     => 'boolean',
        'last_updated_at' => 'datetime',
    ];

    /** @return BelongsTo<ParkingFloor, ParkingSlot> */
    public function floor(): BelongsTo
    {
        return $this->belongsTo(ParkingFloor::class, 'parking_floor_id');
    }

    /** Human-readable identifier for this slot. */
    public function displayLabel(): string
    {
        return $this->label ?? (string) $this->slot_number;
    }
}
