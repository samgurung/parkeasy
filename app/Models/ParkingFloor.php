<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkingFloor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'parking_lot_id',
        'floor_number',
        'slot_count',
    ];

    protected $casts = [
        'floor_number'   => 'integer',
        'slot_count'     => 'integer',
        'parking_lot_id' => 'integer',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ParkingLot::class, 'parking_lot_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(ParkingSlot::class)->orderBy('slot_number');
    }

    public function syncSlots(): void
    {
        $existing = $this->slots()->pluck('slot_number')->all();
        $desired  = range(1, $this->slot_count);

        foreach (array_diff($desired, $existing) as $num) {
            $this->slots()->create(['slot_number' => $num]);
        }
    }
}
