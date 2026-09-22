<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Kiosk extends Model
{
    protected $fillable = [
        'name',
        'key',
        'parking_lot_id',
    ];

    public function parkingLot(): BelongsTo
    {
        return $this->belongsTo(ParkingLot::class, 'parking_lot_id');
    }

    /**
     * Generate a unique, human-friendly identifier for the kiosk URL (?kiosk=<key>).
     */
    public static function makeKey(string $name): string
    {
        return Str::lower(Str::slug($name)).'-'.Str::lower(Str::random(4));
    }
}
