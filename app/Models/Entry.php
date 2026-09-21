<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Entry extends Model
{
    protected $fillable = [
        'vehicle_id',
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

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
