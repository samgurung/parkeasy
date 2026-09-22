<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Entry extends Model
{
    protected $fillable = [
        'vehicle_id',
        'vehicle_type',
        'parking_lot_id',
        'driver_name',
        'vehicle_number',
        'mobile_number',
        'entry_time',
        'exit_time',
        'status',
        'amount',
    ];


    protected $casts = [
        'entry_time' => 'datetime',
        'exit_time'  => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function parkingLot(): BelongsTo
    {
        return $this->belongsTo(ParkingLot::class, 'parking_lot_id');
    }
}
