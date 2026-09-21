<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkingFloor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'floor_number',
        'slot_count',
    ];

    protected $casts = [
        'floor_number' => 'integer',
        'slot_count'   => 'integer',
    ];

    /** @return HasMany<ParkingSlot> */
    public function slots(): HasMany
    {
        return $this->hasMany(ParkingSlot::class)->orderBy('slot_number');
    }

    /**
     * Sync parking_slots rows to match the configured slot_count.
     * Creates missing slots (1..slot_count) and removes excess ones.
     */
    public function syncSlots(): void
    {
        $existing = $this->slots()->pluck('slot_number')->all();
        $desired  = range(1, $this->slot_count);

        $toCreate = array_diff($desired, $existing);
        $toDelete = array_diff($existing, $desired);

        foreach ($toCreate as $num) {
            $this->slots()->create(['slot_number' => $num]);
        }

        if (!empty($toDelete)) {
            $this->slots()->whereIn('slot_number', $toDelete)->delete();
        }
    }
}
